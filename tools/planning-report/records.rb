require 'date'
require 'pathname'
require 'psych'

module Planning
  class Invalid < StandardError; end

  class Records
    KINDS = %w[activities requirements features milestones stories tasks checks].freeze
    PREFIXES = %w[ACT REQ FEAT MS ST TASK CHECK].freeze
    STATUSES = %w[planned in_progress implemented verified].freeze
    FIELDS = {
      'activities' => %w[order],
      'requirements' => [],
      'features' => [],
      'milestones' => %w[order],
      'stories' => %w[feature milestone activity order status scope requirements depends_on blocker],
      'tasks' => %w[story status depends_on blocker],
      'checks' => %w[milestone status]
    }.freeze
    EVIDENCE_ITEM = /\A(\d{4}-\d{2}-\d{2}) — (.+) — \[([^\]]+)\]\(([^()\s]+)\)\z/

    def initialize(source, repo:)
      @source = source
      @repo = File.realpath(repo)
      @groups = KINDS.to_h { |kind| [kind, []] }
      @by_id = {}
      @errors = []
      KINDS.each { |kind| load_group(kind) }
      validate_links if @errors.empty?
      raise Invalid, @errors.join("\n") unless @errors.empty?
    end

    def [](id)
      @by_id.fetch(id)
    end

    def group(kind)
      @groups.fetch(kind).sort_by do |record|
        case kind
        when 'stories'
          [self[record['milestone']]['order'], self[record['activity']]['order'], record['order'], record['id']]
        when 'activities', 'milestones'
          [record['order'], record['id']]
        else
          [record['id']]
        end
      end
    end

    def blockers(record)
      record.fetch('depends_on', []).reject { |id| self[id]['status'] == 'verified' }
    end

    private

    def load_group(kind)
      Dir.glob(File.join(@source, kind, '*.md')).sort.each do |file|
        record = parse(file, kind)
        if @by_id.key?(record['id'])
          problem(record, "duplicate ID #{record['id']} also defined in #{@by_id[record['id']]['file']}")
        else
          @by_id[record['id']] = record
          @groups[kind] << record
        end
      rescue Invalid, Psych::Exception => error
        @errors << "#{file}: #{error.message}"
      end
    end

    def parse(file, kind)
      unless File.file?(file) && !File.symlink?(file)
        raise Invalid, 'record must be a regular Markdown file'
      end
      raw = File.read(file, encoding: 'UTF-8')
      raise Invalid, 'record must be valid UTF-8' unless raw.valid_encoding?
      match = /\A---\r?\n(.*?)\r?\n---\r?\n(.*)\z/m.match(raw)
      raise Invalid, 'expected YAML front matter delimited by ---' unless match

      stream = Psych.parse_stream(match[1], filename: file)
      unless stream.children.size == 1 && stream.children.first.root.is_a?(Psych::Nodes::Mapping)
        raise Invalid, 'front matter must be one YAML mapping'
      end
      validate_yaml(stream.children.first.root)
      fields = Psych.safe_load(match[1], permitted_classes: [], permitted_symbols: [], aliases: false, filename: file)
      fields.delete('client') # Older records can carry copy from the retired export.
      allowed = %w[id title] + FIELDS.fetch(kind)
      reject_unknown(fields, allowed)
      prefix = PREFIXES.fetch(KINDS.index(kind))
      unless fields['id'].is_a?(String) && fields['id'].match?(/\A#{prefix}-[A-Z0-9]+(?:-[A-Z0-9]+)*\z/)
        raise Invalid, "id must use the #{prefix}- prefix and uppercase letters, digits, and hyphens"
      end
      require_text(fields['title'], 'title')
      validate_fields(fields, kind)

      sections = parse_body(match[2])
      criteria = sections.delete('Acceptance criteria')
      evidence = sections.delete('Evidence')
      sections.delete('') if sections[''].empty?
      if %w[requirements stories tasks checks].include?(kind)
        criteria = bullets(criteria, 'Acceptance criteria')
      elsif criteria
        criteria = bullets(criteria, 'Acceptance criteria')
      end
      evidence = evidence ? bullets(evidence, 'Evidence').map { |line| parse_evidence(line, file) } : []
      if fields['status'] == 'verified' && evidence.empty?
        raise Invalid, 'verified status requires a dated Evidence entry with a result and reference'
      end
      fields.merge('kind' => kind, 'file' => file, 'sections' => sections,
                   'acceptance' => criteria || [], 'evidence' => evidence)
    end

    def validate_yaml(node)
      raise Invalid, 'explicit YAML tags are forbidden' if node.respond_to?(:tag) && node.tag
      case node
      when Psych::Nodes::Alias
        raise Invalid, 'YAML aliases are forbidden'
      when Psych::Nodes::Mapping
        keys = []
        node.children.each_slice(2) do |key, value|
          raise Invalid, 'YAML keys must be scalars' unless key.is_a?(Psych::Nodes::Scalar)
          validate_yaml(key)
          raise Invalid, "duplicate YAML key #{key.value}" if keys.include?(key.value)
          keys << key.value
          validate_yaml(value)
        end
      when Psych::Nodes::Sequence
        node.children.each { |child| validate_yaml(child) }
      end
    end

    def validate_fields(fields, kind)
      if FIELDS.fetch(kind).include?('order')
        order = fields['order']
        raise Invalid, 'order must be a positive integer' unless order.is_a?(Integer) && order.positive?
      end
      if FIELDS.fetch(kind).include?('status') && !STATUSES.include?(fields['status'])
        raise Invalid, "status must be one of #{STATUSES.join(', ')}"
      end
      case kind
      when 'stories'
        %w[feature milestone activity].each { |key| require_text(fields[key], key) }
        unless %w[required optional].include?(fields['scope'])
          raise Invalid, 'scope must be required or optional'
        end
        require_ids(fields['requirements'], 'requirements')
      when 'tasks'
        require_text(fields['story'], 'story')
      when 'checks'
        require_text(fields['milestone'], 'milestone')
      end
      require_ids(fields['depends_on'], 'depends_on') if fields.key?('depends_on')
      require_text(fields['blocker'], 'blocker') if fields.key?('blocker')
    end

    def reject_unknown(fields, allowed)
      extra = fields.keys - allowed
      raise Invalid, "unknown field #{extra.map(&:inspect).join(', ')}" unless extra.empty?
    end

    def require_text(value, label)
      return if value.is_a?(String) && !value.strip.empty?
      raise Invalid, "#{label} must be nonempty text"
    end

    def require_ids(ids, label)
      unless ids.is_a?(Array) && ids.all? { |id| id.is_a?(String) && !id.empty? } && ids.uniq == ids
        raise Invalid, "#{label} must be a list of unique IDs (use [] for none)"
      end
    end

    def parse_body(body)
      sections = { '' => [] }
      heading = ''
      body.each_line do |line|
        line = line.chomp
        if line.start_with?('## ')
          heading = line.delete_prefix('## ').strip
          raise Invalid, "duplicate or empty Markdown section #{heading.inspect}" if sections.key?(heading)
          sections[heading] = []
        else
          sections[heading] << line
        end
      end
      sections
    end

    def bullets(lines, label)
      items = (lines || []).reject { |line| line.strip.empty? }
      unless !items.empty? && items.all? { |line| line.start_with?('- ') && !line[2..-1].strip.empty? }
        raise Invalid, "#{label} must be a nonempty Markdown list with one '- ' item per line"
      end
      items.map { |line| line[2..-1].strip }
    end

    def parse_evidence(line, file)
      match = EVIDENCE_ITEM.match(line)
      unless match
        raise Invalid, 'Evidence item must use YYYY-MM-DD — result — [label](reference)'
      end
      date, result, label, target = match.captures
      begin
        Date.iso8601(date)
      rescue Date::Error
        raise Invalid, "invalid Evidence date #{date}"
      end
      external = target.match?(%r{\Ahttps://github\.com/[\w.-]+/[\w.-]+/(?:pull/\d+|actions/runs/\d+(?:/job/\d+)?)(?:#[\w-]+)?\z})
      unless external
        path = target.split('#', 2).first
        if !path || target.include?(':') || Pathname.new(path).absolute?
          raise Invalid, "unsupported Evidence reference #{target}"
        end
        path = File.expand_path(path, File.dirname(file))
        roots = %w[docs tests tools].map { |dir| File.join(@repo, dir) + File::SEPARATOR }
        unless File.file?(path) && roots.any? { |root| File.realpath(path).start_with?(root) }
          raise Invalid, "Evidence reference #{target} must point to a local file in docs/, tests/, or tools/"
        end
        target = Pathname.new(path).relative_path_from(Pathname.new(@repo)).to_s
        fragment = match[4].split('#', 2)[1]
        target = "#{target}##{fragment}" if fragment
      end
      { 'date' => date, 'result' => result, 'label' => label, 'reference' => target, 'external' => external }
    end

    def validate_links
      %w[activities milestones].each do |kind|
        validate_positions(@groups[kind], %w[order])
      end
      validate_positions(@groups['stories'], %w[milestone activity order])
      @groups['stories'].each do |story|
        link(story, 'feature', story['feature'], %w[features])
        link(story, 'milestone', story['milestone'], %w[milestones])
        link(story, 'activity', story['activity'], %w[activities])
        story['requirements'].each { |id| link(story, 'requirements', id, %w[requirements]) }
      end
      @groups['tasks'].each { |task| link(task, 'story', task['story'], %w[stories]) }
      @groups['checks'].each { |check| link(check, 'milestone', check['milestone'], %w[milestones]) }
      work = @groups['stories'] + @groups['tasks']
      work.each do |record|
        record.fetch('depends_on', []).each { |id| link(record, 'depends_on', id, %w[stories tasks]) }
      end
      validate_cycles(work) if @errors.empty?
    end

    def validate_positions(records, fields)
      records.group_by { |record| record.values_at(*fields) }.each_value do |matches|
        next if matches.size < 2
        matches.drop(1).each { |record| problem(record, "duplicate position of #{matches.first['id']}") }
      end
    end

    def link(record, field, id, kinds)
      target = @by_id[id]
      if !target
        problem(record, "#{field} references unknown ID #{id}")
      elsif !kinds.include?(target['kind'])
        problem(record, "#{field} references #{id}, expected #{kinds.join(' or ')}")
      end
      target
    end

    def validate_cycles(work)
      visited = {}
      visit = lambda do |record, path|
        id = record['id']
        if path.include?(id)
          problem(record, "dependency cycle: #{(path.drop_while { |member| member != id } + [id]).join(' -> ')}")
          return
        end
        return if visited[id]
        record.fetch('depends_on', []).each { |dependency| visit.call(self[dependency], path + [id]) }
        visited[id] = true
      end
      work.each { |record| visit.call(record, []) }
    end

    def problem(record, message)
      @errors << "#{record['file']}: #{message}"
    end
  end
end
