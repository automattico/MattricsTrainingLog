require_relative 'planning'

def assert(condition, message)
  raise "Test failed: #{message}" unless condition
end

class Fixture
  def initialize(repo)
    @repo = repo
    @source = File.join(repo, 'docs', 'planning')
    @output = File.join(repo, 'output')
    @build = Planning::Build.new(@output, File.expand_path('../..', __dir__))
    FileUtils.mkdir_p(@source)
    File.write(File.join(repo, 'docs/fixture-proof.md'), "# Synthetic test result\n")
    seed
  end

  def write(kind, id, title:, body: '', **fields)
    directory = File.join(@source, kind)
    FileUtils.mkdir_p(directory)
    path = File.join(directory, "#{id.downcase}.md")
    File.write(path, Psych.dump({ 'id' => id, 'title' => title }.merge(fields.transform_keys(&:to_s))) + "---\n\n" + body)
    path
  end

  def replace(path, from, to)
    before = File.read(path)
    assert(before.include?(from), "fixture contains #{from}")
    File.write(path, before.sub(from, to))
    yield if block_given?
  ensure
    File.write(path, before) if block_given? && before
  end

  def records
    Planning::Records.new(@source, repo: @repo)
  end

  def build(mode = 'staging')
    @build.write(records, mode: mode, context: 'Fixture preview')
  end

  def files(mode = 'staging')
    directory = File.join(@output, mode, 'internal')
    Dir.children(directory).sort.to_h { |name| [name, File.binread(File.join(directory, name))] }
  end

  def file(relative)
    File.join(@source, relative)
  end

  def output(relative)
    File.join(@output, relative)
  end

  def proof
    "\n## Evidence\n\n- 2026-11-19 — Synthetic round trip passed. — [Fixture proof](../../fixture-proof.md)\n"
  end

  def acceptance
    "\n## Acceptance criteria\n\n- Verify the sample.\n"
  end

  private

  def story(id, status, order, scope: 'required', body: '', **fields)
    write('stories', id, title: "INTERNAL TITLE #{id}", body: body + acceptance + (status == 'verified' ? proof : ''),
          activity: 'ACT-WORK', feature: 'FEAT-CORE', milestone: 'MS-ONE', scope: scope,
          order: order, status: status, requirements: ['REQ-LINKED'], **fields)
  end

  def seed
    write('activities', 'ACT-WORK', title: 'Internal activity', order: 10)
    write('features', 'FEAT-CORE', title: 'Internal feature')
    write('features', 'FEAT-EMPTY', title: 'Empty feature')
    write('milestones', 'MS-ONE', title: 'Internal release', order: 10)
    write('milestones', 'MS-TWO', title: 'Empty release', order: 20)
    write('requirements', 'REQ-LINKED', title: 'Linked requirement', body: acceptance)
    write('requirements', 'REQ-GAP', title: 'Missing coverage requirement', body: acceptance)
    first = story('ST-FIRST', 'verified', 10, body: '<script>PRIVATE_NOTES</script>')
    File.open(first, 'a') do |file|
      file.puts '- 2026-11-19 — Reviewed synthetic evidence. — [PRIVATE_PR](https://github.com/private-fixture-org/report/pull/1)'
    end
    story('ST-PROMOTE', 'implemented', 20, client: { 'title' => 'LEGACY_CLIENT_COPY' })
    story('ST-SECRET', 'verified', 30,
          body: "PRIVATE_STORY_TEXT\n\n## Notes\n\nPrivate link https://private.example/planning")
    story('ST-BLOCKED', 'planned', 40, scope: 'optional', blocker: 'PRIVATE_MANUAL_BLOCKER')
    story('ST-AFTER', 'planned', 50, scope: 'optional', depends_on: ['ST-PROMOTE'])
    story('ST-READY', 'planned', 60, scope: 'optional', depends_on: ['ST-FIRST'])
    story('ST-ACTIVE', 'in_progress', 70, scope: 'optional')
    write('tasks', 'TASK-INTERNAL', title: 'PRIVATE_TASK_TITLE', story: 'ST-PROMOTE', status: 'planned',
          depends_on: ['ST-PROMOTE'], body: acceptance)
    write('checks', 'CHECK-OPEN', title: 'Internal release check', milestone: 'MS-ONE', status: 'planned',
          body: acceptance)
  end
end

def artifact_data(files, total:, verified:)
  assert(files.keys == Planning::Build::ARTIFACT_FILES, 'artifact has exactly the allowlisted files')
  data = JSON.parse(files.fetch('report.json'))
  assert(data.fetch('progress').values_at('required', 'verified') == [total, verified], 'scope progress counts')
  html = files.fetch('index.html')
  assert(html.include?("<progress value=\"#{verified}\" max=\"#{total}\" aria-label=\"Overall\""), 'progress bar matches JSON')
  ids = html.scan(/\bid="([^"]+)"/).flatten
  assert(ids.uniq == ids, 'HTML anchors are unique')
  hrefs = html.scan(/\b(?:href|src)="([^"]+)"/).flatten
  missing = hrefs.reject do |href|
    href.start_with?('#') ? ids.include?(href.delete_prefix('#')) : files.key?(href) || href.start_with?('https://')
  end
  assert(missing.empty?, "report links resolve: #{missing.join(', ')}")
  font_paths = files.fetch('styles.css').scan(/url\("([^"]+\.woff2)"\)/).flatten
  assert(font_paths.any? && font_paths.all? { |path| files.fetch(path).start_with?('wOF2') }, 'all webfonts are bundled')
  data
end

def invalid(fixture, file, message)
  fixture.records
  raise 'Test failed: invalid records were accepted'
rescue Planning::Invalid => error
  assert(error.message.include?(file), "error identifies #{file}")
  assert(error.message.include?(message), "error explains #{message}: #{error.message}")
end

Dir.mktmpdir('mattrics-report-test-') do |repo|
  fixture = Fixture.new(repo)
  fixture.build
  first = fixture.files
  data = artifact_data(first, total: 3, verified: 2)
  assert(data['progress']['optional'] == 4, 'optional work is counted separately')
  assert(data['milestones'].last['readiness'] == 'No required stories', 'empty milestone is not ready')
  assert(data['features'].last['progress']['status'] == 'No required stories', 'empty feature is labelled')
  assert(data['requirements'].map { |row| row['coverage'] } == ['No coverage', 'Incomplete evidence'],
         'missing coverage and incomplete evidence are distinguished')
  assert(data['next_work']['ready'] == ['st-ready'], 'ready queue requires planned status and verified dependencies')
  assert(data['next_work']['verification'] == ['st-promote'], 'implemented work remains visible')
  assert(data['next_work']['blocked'] == %w[task-internal st-blocked st-after], 'manual and prerequisite blockers in map order')
  assert(first['index.html'].include?('&lt;script&gt;PRIVATE_NOTES&lt;/script&gt;'), 'internal Markdown HTML is escaped')
  assert(!first['index.html'].include?('<script>'), 'internal Markdown is inert')
  assert(first['index.html'].include?('href="https://github.com/private-fixture-org/report/pull/1"'),
         'internal evidence link is a real link')
  workspace = first['index.html']
  assert(!workspace.include?('data-view-link') && !workspace.include?('LEGACY_CLIENT_COPY') &&
         !first['report.json'].include?('LEGACY_CLIENT_COPY'), 'only the private workspace is rendered')
  assert(workspace.scan(/data-tab>/).size == 6, 'each section has one tab')
  assert(workspace.include?('<details id="band-ms-one" class="band release-checks" open'),
         'milestone awaiting verification is color-coded and open on the map')
  assert(workspace.include?('<details id="band-ms-two" class="band release-planned"'),
         'planned milestone is color-coded on the map')

  stale = fixture.output('staging/client')
  FileUtils.mkdir_p(stale)
  File.write(File.join(stale, 'index.html'), 'OLD_CLIENT_OUTPUT')
  fixture.build
  assert(fixture.files == first, 'unchanged inputs reproduce the private artifact')
  assert(Dir.children(fixture.output('staging')) == ['internal'], 'rebuild removes the previous standalone client folder')
  hidden_story = fixture.file('stories/st-secret.md')
  hidden_contents = File.read(hidden_story)
  File.delete(hidden_story)
  fixture.build
  rebuilt = fixture.files
  artifact_data(rebuilt, total: 2, verified: 1)
  assert(!rebuilt['report.json'].include?('PRIVATE_STORY_TEXT'), 'deleted records disappear from generated data')
  File.write(hidden_story, hidden_contents)

  promote = fixture.file('stories/st-promote.md')
  fixture.replace(promote, 'status: implemented', 'status: verified') do
    invalid(fixture, 'st-promote.md', 'verified status requires')
    File.open(promote, 'a') { |file| file.write(fixture.proof) }
    fixture.build
    promoted = fixture.files
    internal = artifact_data(promoted, total: 3, verified: 3)
    detail = promoted['index.html'].split('id="st-promote"', 2).last.split('</details>', 2).first
    assert(detail.include?('✓</span> Verified'.b), 'verified story has a checkmark')
    assert(promoted['index.html'].include?('id="band-ms-one" class="band release-checks"'),
           'open release checks change the map color after all required stories verify')
    assert(internal['milestones'].first['readiness'] == 'Release checks open', 'open check prevents release readiness')
    assert(internal['next_work']['ready'] == %w[st-after st-ready], 'verification unlocks dependent stories')
    assert(internal['next_work']['verification'].empty?, 'verified stories leave the verification queue')
    check = fixture.file('checks/check-open.md')
    fixture.replace(check, 'status: planned', 'status: verified') do
      invalid(fixture, 'check-open.md', 'verified status requires')
      File.open(check, 'a') { |file| file.write(fixture.proof) }
      fixture.build('release')
      assert(JSON.parse(fixture.files('release')['report.json'])['milestones'].first['readiness'] == 'Ready',
             'verified gate and required stories make the release ready')
      assert(fixture.files('release')['index.html'].include?('id="band-ms-one" class="band release-ready"'),
             'verified release checks change the map color to ready')
      assert(Dir.children(fixture.output('release')) == ['internal'], 'release produces only the private workspace')
    end
  end

  fixture.replace(promote, 'status: implemented', 'status: invented') { invalid(fixture, 'st-promote.md', 'status must be') }
  fixture.replace(promote, 'REQ-LINKED', 'REQ-ABSENT') { invalid(fixture, 'st-promote.md', 'requirements references unknown ID REQ-ABSENT') }
  fixture.replace(promote, 'feature: FEAT-CORE', 'feature: MS-ONE') { invalid(fixture, 'st-promote.md', 'expected features') }
  fixture.replace(promote, 'milestone: MS-ONE', 'milestone: MS-ABSENT') { invalid(fixture, 'st-promote.md', 'milestone references unknown ID') }
  fixture.replace(promote, 'status: implemented', "status: implemented\ndepends_on: [TASK-INTERNAL]") do
    invalid(fixture, 'st-promote.md', 'dependency cycle: ST-PROMOTE -> TASK-INTERNAL -> ST-PROMOTE')
  end
  fixture.replace(promote, 'status: implemented', "status: implemented\nstatus: verified") { invalid(fixture, 'st-promote.md', 'duplicate YAML key status') }
  fixture.replace(promote, 'status: implemented', 'status: !ruby/object:Object {}') { invalid(fixture, 'st-promote.md', 'explicit YAML tags are forbidden') }
  fixture.replace(promote, 'status: implemented', 'status: *missing') { invalid(fixture, 'st-promote.md', 'YAML aliases are forbidden') }
  fixture.replace(promote, 'id: ST-PROMOTE', 'id: BAD-PROMOTE') { invalid(fixture, 'st-promote.md', 'must use the ST- prefix') }
  fixture.replace(fixture.file('stories/st-first.md'), '../../fixture-proof.md', '../../missing-proof.md') do
    invalid(fixture, 'st-first.md', 'Evidence reference')
  end
  FileUtils.cp(promote, fixture.file('stories/duplicate.md'))
  invalid(fixture, 'st-promote.md', 'duplicate ID ST-PROMOTE')
end

puts 'Planning report fixture passed: validation, progress, queues, links, single workspace, and stale export cleanup.'
