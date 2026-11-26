require 'cgi'
require 'json'

module Planning
  class Report
    STATUS_LABELS = {
      'planned' => 'Planned', 'in_progress' => 'In progress',
      'implemented' => 'Implemented', 'verified' => 'Verified'
    }.freeze

    attr_reader :data

    def initialize(records, context:)
      @records = records
      groups = Records::KINDS.to_h { |kind| [kind, records.group(kind)] }
      @data = groups.transform_values { |group| group.map { |record| project(record) } }
      @data['audience'] = 'internal'
      @data['context'] = context
      stories = @data.fetch('stories')
      @data['progress'] = progress(stories)
      @data.fetch('features').each do |feature|
        feature['progress'] = progress(stories.select { |story| story['feature'] == feature['key'] })
      end
      @data.fetch('milestones').each do |milestone|
        in_milestone = stories.select { |story| story['milestone'] == milestone['key'] }
        checks = @data.fetch('checks').select { |check| check['milestone'] == milestone['key'] }
        milestone['progress'] = progress(in_milestone)
        milestone['checks'] = checks.map { |check| check['key'] }
        milestone['readiness'] = readiness(milestone['progress'], checks)
      end
      @data.fetch('requirements').each do |requirement|
        linked = stories.select { |story| story['requirements'].include?(requirement['key']) }
        requirement['stories'] = linked.map { |story| story['key'] }
        requirement['coverage'] = if linked.empty?
                                    'No coverage'
                                  elsif linked.all? { |story| story['status'] == 'verified' }
                                    'Evidence complete'
                                  else
                                    'Incomplete evidence'
                                  end
      end
      tasks = @data.fetch('tasks').group_by { |task| task['story'] }
      all_work = stories.flat_map { |story| [story] + tasks.fetch(story['key'], []) }
      @data['next_work'] = {
        'ready' => stories.select { |story| story['status'] == 'planned' && !blocked?(story) }.map { |story| story['key'] },
        'verification' => stories.select { |story| story['status'] == 'implemented' }.map { |story| story['key'] },
        'blocked' => all_work.select { |work| work['status'] != 'verified' && blocked?(work) }.map { |work| work['key'] }
      }
    end

    private

    def project(record)
      item = { 'key' => record.fetch('id').downcase }
      item.merge!(record.slice('id', 'title', 'sections', 'acceptance', 'evidence', 'blocker'))
      %w[requirements depends_on].each do |field|
        item[field] = record[field].map(&:downcase) if record.key?(field)
      end
      if %w[stories tasks].include?(record['kind'])
        item['dependency_blockers'] = @records.blockers(record).map(&:downcase)
      end
      %w[activity feature milestone story].each do |field|
        item[field] = record[field].downcase if record.key?(field)
      end
      item.merge(record.slice('status', 'scope'))
    end

    def blocked?(item)
      item['blocker'] || !item.fetch('dependency_blockers').empty?
    end

    def progress(stories)
      required, optional = stories.partition { |story| story['scope'] == 'required' }
      verified = required.count { |story| story['status'] == 'verified' }
      status = if required.empty?
                 'No required stories'
               elsif verified == required.size
                 'Verified'
               elsif required.all? { |story| %w[implemented verified].include?(story['status']) }
                 'Implemented'
               elsif required.any? { |story| story['status'] != 'planned' }
                 'In progress'
               else
                 'Planned'
               end
      {
        'verified' => verified, 'required' => required.size, 'status' => status,
        'optional' => optional.size, 'optional_verified' => optional.count { |story| story['status'] == 'verified' }
      }
    end

    def readiness(progress, checks)
      return 'No required stories' if progress['required'].zero?
      return 'Required stories open' if progress['required'] != progress['verified']
      return 'No release checks' if checks.empty?
      return 'Release checks open' unless checks.all? { |check| check['status'] == 'verified' }

      'Ready'
    end
  end

  class Html
    def initialize(report)
      @data = report.data
      @items = Records::KINDS.flat_map { |kind| @data.fetch(kind, []) }
                             .to_h { |record| [record.fetch('key'), record] }
    end

    def render
      title = 'Mattrics planning report'
      <<~HTML
        <!doctype html>
        <html lang="en">
        <head>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <title>#{title}</title>
          <link rel="icon" href="favicon.svg" type="image/svg+xml">
          <link rel="stylesheet" href="brand.css">
          <link rel="stylesheet" href="styles.css">
          <script src="workspace.js" defer></script>
        </head>
        <body>
          <a class="skip" href="#main">Skip to main content</a>
          <header class="masthead"><div class="wrap">
            <div class="report-heading">
              <img class="brand-mark" src="logo-mark.svg" alt="" width="64" height="58">
              <div>
                <h1>#{title}</h1>
                <p class="context">#{h(@data.fetch('context'))}</p>
              </div>
            </div>
          </div></header>
          <main class="wrap" id="main" tabindex="-1">
            #{workspace}
          </main>
        </body>
        </html>
      HTML
    end

    private

    def workspace
      sections = {
        'story-map' => ['Story map', story_map],
        'releases' => ['Releases', releases],
        'features' => ['Features', features],
        'stories' => ['Stories', details],
        'requirements' => ['Requirements', requirements],
        'next-work' => ['Next work', next_work]
      }
      tabs = sections.map do |key, (title, _)|
        %(<a id="#{h("tab-#{key}")}" href="##{h(key)}" data-tab>#{title}</a>)
      end
      panels = sections.map do |key, (title, content)|
        %(<section class="view-panel" id="#{h(key)}" data-section="#{key}" aria-labelledby="#{h("heading-#{key}")}"><h2 id="#{h("heading-#{key}")}">#{title}</h2>#{content}</section>)
      end
      <<~HTML
        <div class="report-toolbar">
          <nav class="section-tabs" aria-label="Report sections" data-tabs>#{tabs.join}</nav>
          <div class="overall">#{progress(@data.fetch('progress'), 'Overall', compact: true)}</div>
        </div>
        #{panels.join}
      HTML
    end

    def h(value)
      CGI.escapeHTML(value.to_s)
    end

    def list(items)
      return '' if items.empty?
      "<ul>#{items.map { |item| "<li>#{item}</li>" }.join}</ul>"
    end

    def link(key, with_status: false)
      item = @items.fetch(key)
      state = with_status ? " · #{Report::STATUS_LABELS.fetch(item.fetch('status'))}" : ''
      %(<a href="##{h(key)}">#{h(item['title'])}</a>#{state})
    end

    def status(item)
      flag = item['status'] == 'verified' ? '<span aria-hidden="true">✓</span> ' : ''
      %(<span class="status #{h(item['status'])}">#{flag}#{Report::STATUS_LABELS.fetch(item.fetch('status'))}</span>)
    end

    def progress(counts, label, compact: false)
      if counts['required'].zero?
        text = 'No required stories'
        bar = ''
      else
        text = "#{counts['verified']} of #{counts['required']} required stories verified"
        bar = %(<progress value="#{counts['verified']}" max="#{counts['required']}" aria-label="#{h(label)}">#{h(text)}</progress>)
      end
      optional = if !compact && counts['optional'].positive?
                   %(<p class="muted">#{counts['optional_verified']} of #{counts['optional']} optional stories verified</p>)
                 end
      %(<div class="progress"><p>#{text}</p>#{bar}#{optional}</div>)
    end

    def release_state(milestone)
      case milestone.fetch('readiness')
      when 'Ready' then ['ready', milestone['readiness']]
      when 'Release checks open', 'No release checks' then ['checks', milestone['readiness']]
      when 'Required stories open'
        case milestone.dig('progress', 'status')
        when 'Implemented' then ['checks', 'Awaiting verification']
        when 'In progress' then ['active', 'In progress']
        else ['planned', 'Planned']
        end
      else ['planned', milestone['readiness']]
      end
    end

    def disclosure(id:, title:, body:, meta: nil, classes: 'panel', expanded: false)
      <<~HTML
        <details id="#{h(id)}" class="#{classes}"#{' open' if expanded}>
          <summary class="disclosure-heading">
            <span class="disclosure-title">#{h(title)}</span>
            #{%(<span class="disclosure-meta">#{meta}</span>) if meta}
          </summary>
          <div class="disclosure-body">#{body}</div>
        </details>
      HTML
    end

    def release_badge(label)
      %(<span class="release-badge">#{h(label)}</span>)
    end

    def count_label(counts)
      return 'No required stories' if counts['required'].zero?
      "#{counts['verified']}/#{counts['required']} verified"
    end

    def story_map
      milestones = @data.fetch('milestones')
      activities = @data.fetch('activities')
      stories_by_cell = @data.fetch('stories').group_by { |story| story.values_at('milestone', 'activity') }
      expanded = milestones.select { |milestone| %w[active checks].include?(release_state(milestone).first) }
      expanded = milestones.first(1) if expanded.empty?
      bands = milestones.map do |milestone|
        columns = activities.map do |activity|
          stories = stories_by_cell.fetch([milestone['key'], activity['key']], [])
          cards = stories.map do |story|
            %(<li class="story-card">#{link(story['key'])}<div class="card-meta">#{status(story)}#{optional(story)}</div></li>)
          end
          <<~HTML
            <div class="lane#{' empty-lane' if cards.empty?}">
              <h4>#{h(activity['title'])}</h4>
              #{"<ul>#{cards.join}</ul>" unless cards.empty?}
            </div>
          HTML
        end
        state, label = release_state(milestone)
        meta = %(#{release_badge(label)}<span class="count">#{count_label(milestone['progress'])}</span>)
        body = %(<div class="lanes">#{columns.join}</div><p class="band-link"><a href="##{h(milestone['key'])}">View release details</a></p>)
        disclosure(id: "band-#{milestone['key']}", title: milestone['title'], meta: meta, body: body,
                   classes: "band release-#{state}", expanded: expanded.include?(milestone))
      end
      bands.empty? ? '<p>No milestones.</p>' : %(<div class="map-bands">#{bands.join}</div>)
    end

    def releases
      rows = @data.fetch('milestones').map do |milestone|
        checks = milestone.fetch('checks').map do |key|
          check = @items.fetch(key)
          <<~HTML
            <li id="#{h(key)}">
              <div>#{h(check['title'])} #{status(check)}</div>
              #{summary(check)}
              #{criteria(check['acceptance'])}
              #{evidence(check)}
            </li>
          HTML
        end
        state, label = release_state(milestone)
        body = <<~HTML
            #{progress(milestone.fetch('progress'), milestone['title'])}
            #{summary(milestone)}
            <h4>Release checks</h4>
            #{checks.empty? ? '<p class="muted">No checks in this scope.</p>' : %(<ul class="checks">#{checks.join}</ul>)}
        HTML
        meta = %(#{release_badge(label)}<span class="count">#{count_label(milestone['progress'])}</span>)
        disclosure(id: milestone['key'], title: milestone['title'], meta: meta, body: body,
                   classes: "panel release-#{state}")
      end
      %(<div class="panels">#{rows.join}</div>)
    end

    def features
      stories_by_feature = @data.fetch('stories').group_by { |story| story['feature'] }
      rows = @data.fetch('features').map do |feature|
        counts = feature.fetch('progress')
        stories = stories_by_feature.fetch(feature['key'], [])
        body = <<~HTML
            #{summary(feature)}
            #{progress(counts, feature['title'])}
            #{list(stories.map { |story| link(story['key'], with_status: true) })}
        HTML
        disclosure(id: feature['key'], title: feature['title'], meta: %(<span class="count">#{count_label(counts)}</span>), body: body)
      end
      rows.empty? ? '<p>No features in this scope.</p>' : %(<div class="panels">#{rows.join}</div>)
    end

    def requirements
      rows = @data.fetch('requirements').map do |requirement|
        body = <<~HTML
            #{summary(requirement)}
            #{criteria(requirement['acceptance'])}
            #{list(requirement.fetch('stories').map { |key| link(key, with_status: true) })}
        HTML
        disclosure(id: requirement['key'], title: requirement['title'], meta: %(<span class="count">#{h(requirement['coverage'])}</span>), body: body)
      end
      %(<div class="panels">#{rows.join}</div>)
    end

    def next_work
      headings = {
        'ready' => 'Ready stories',
        'verification' => 'Implemented, awaiting verification',
        'blocked' => 'Blocked work'
      }
      panels = headings.map do |key, title|
        items = @data.fetch('next_work').fetch(key).map do |id|
          "#{link(id, with_status: true)}#{blocker_details(@items.fetch(id)) if key == 'blocked'}"
        end
        disclosure(id: "queue-#{key}", title: title, body: items.empty? ? '<p>None.</p>' : list(items),
                   meta: %(<span class="count">#{items.length}</span>), expanded: !items.empty?)
      end
      %(<div class="queue">#{panels.join}</div>)
    end

    def details
      tasks_by_story = @data.fetch('tasks', []).group_by { |task| task['story'] }
      rows = @data.fetch('stories').group_by { |story| story['milestone'] }.map do |milestone, stories|
        cards = stories.map { |story| story_detail(story, tasks_by_story.fetch(story['key'], [])) }
        disclosure(id: "group-#{milestone}", title: @items.fetch(milestone)['title'],
                   meta: %(<span class="count">#{stories.size} #{stories.size == 1 ? 'story' : 'stories'}</span>),
                   body: %(<div class="panels">#{cards.join}</div>), classes: 'panel story-group')
      end
      rows.empty? ? '<p>No stories in this scope.</p>' : %(<div class="map-bands">#{rows.join}</div>)
    end

    def story_detail(story, story_tasks)
      tasks = story_tasks.map { |task| task_detail(task) }
      requirements = story.fetch('requirements', []).map { |key| link(key) }
      body = <<~HTML
        <p class="muted"><code>#{h(story['id'])}</code></p>
        <p>#{link(story['feature'])} · #{link(story['milestone'])}</p>
        #{summary(story)}
        #{criteria(story['acceptance'])}
        #{blocker_details(story)}
        #{dependencies(story)}
        #{requirements.empty? ? '' : "<h4>Requirements</h4>#{list(requirements)}"}
        #{evidence(story)}
        #{tasks.empty? ? '' : "<h4>Tasks</h4>#{tasks.join}"}
      HTML
      disclosure(id: story['key'], title: story['title'], meta: "#{status(story)}#{optional(story)}", body: body)
    end

    def task_detail(task)
      <<~HTML
        <div id="#{h(task['key'])}" class="task">
          <h5>#{h(task['title'])}</h5>
          #{status(task)}
          #{summary(task)}
          #{criteria(task['acceptance'], 'h6')}
          #{dependencies(task, 'h6')}
          #{blocker_details(task)}
          #{evidence(task, 'h6')}
        </div>
      HTML
    end

    def optional(story)
      '<span class="optional">Optional</span>' if story['scope'] == 'optional'
    end

    def blocker_details(item)
      notes = []
      notes << "Manual blocker: #{h(item['blocker'])}" if item['blocker']
      item.fetch('dependency_blockers').each do |key|
        notes << "Prerequisite: #{link(key, with_status: true)}"
      end
      list(notes)
    end

    def dependencies(item, heading = 'h4')
      items = item.fetch('depends_on', []).map { |key| link(key, with_status: true) }
      "<#{heading}>Dependencies</#{heading}>#{list(items)}" unless items.empty?
    end

    def criteria(items, heading = 'h4')
      "<#{heading}>Acceptance criteria</#{heading}>#{list(items.map { |item| inline(item) })}" unless items.empty?
    end

    def evidence(item, heading = 'h4')
      entries = item.fetch('evidence').map do |proof|
        reference = h(proof['reference'])
        location = if proof['external']
                     %(<a href="#{reference}">#{h(proof['label'])}</a>)
                   else
                     "#{h(proof['label'])} · <code>#{reference}</code>"
                   end
        %(<time datetime="#{h(proof['date'])}">#{h(proof['date'])}</time> · #{inline(proof['result'])} · #{location})
      end
      "<#{heading}>Evidence</#{heading}>#{list(entries)}" unless entries.empty?
    end

    def summary(item)
      item.fetch('sections').map do |heading, lines|
        label = heading.empty? ? '' : "<p><strong>#{h(heading)}</strong></p>"
        blocks = lines.join("\n").split(/\n[ \t]*\n+/).map do |block|
          block = block.strip
          next '' if block.empty?
          if block.lines.all? { |line| line.start_with?('- ') }
            list(block.lines.map { |line| inline(line.chomp.delete_prefix('- ')) })
          else
            "<p>#{inline(block.lines.map(&:strip).join(' '))}</p>"
          end
        end
        label + blocks.join
      end.join
    end

    def inline(value)
      h(value).gsub(/\x60([^\x60]+)\x60/, '<code>\1</code>').gsub(/\*\*([^*]+)\*\*/, '<strong>\1</strong>')
    end
  end
end
