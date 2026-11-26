#!/usr/bin/env ruby

require 'fileutils'
require 'open3'
require 'tmpdir'
require_relative 'records'
require_relative 'report'

module Planning
  class Build
    MODES = %w[development staging release].freeze
    ASSET_SOURCES = {
      'styles.css' => 'tools/planning-report/styles.css',
      'workspace.js' => 'tools/planning-report/workspace.js',
      'brand.css' => 'tools/planning-report/brand.css',
      'logo-mark.svg' => 'public/icons/logo-mark.svg',
      'favicon.svg' => 'public/icons/favicon.svg'
    }.merge(%w[
      barlow-condensed-latin-600-normal.woff2 barlow-condensed-latin-700-normal.woff2
      dm-sans-latin-400-normal.woff2 dm-sans-latin-500-normal.woff2 dm-mono-latin-400-normal.woff2
      LICENSE-barlow-condensed.txt LICENSE-dm-sans.txt LICENSE-dm-mono.txt
    ].to_h { |name| [name, "public/assets/fonts/#{name}"] }).freeze
    ARTIFACT_FILES = (%w[index.html report.json] + ASSET_SOURCES.keys).sort.freeze

    def initialize(output, repo)
      @output = output
      @repo = repo
    end

    def write(records, mode:, context:)
      raise Invalid, "Unknown mode #{mode}" unless MODES.include?(mode)
      raise Invalid, 'Output directory must not be a symlink' if File.symlink?(@output)

      FileUtils.mkdir_p(@output, mode: 0o700)
      File.open(File.join(@output, '.build.lock'), File::RDWR | File::CREAT, 0o600) do |lock|
        lock.flock(File::LOCK_EX)
        Dir.mktmpdir('build-', @output) do |workspace|
          stage = File.join(workspace, mode)
          report = Report.new(records, context: context)
          directory = File.join(stage, 'internal')
          FileUtils.mkdir_p(directory)
          File.write(File.join(directory, 'index.html'), Html.new(report).render)
          File.write(File.join(directory, 'report.json'), JSON.pretty_generate(report.data) + "\n")
          ASSET_SOURCES.each do |name, source|
            FileUtils.cp(File.join(@repo, source), File.join(directory, name))
          end
          publish(stage, File.join(@output, mode), File.join(workspace, 'previous'))
        end
      end
    end

    private

    def publish(stage, destination, backup)
      File.rename(destination, backup) if File.exist?(destination) || File.symlink?(destination)
      File.rename(stage, destination)
    rescue SystemCallError
      File.rename(backup, destination) if File.exist?(backup) || File.symlink?(backup)
      raise
    end
  end

  class CLI
    USAGE = 'Usage: ruby tools/planning-report/planning.rb validate | build [development|staging|release]'.freeze

    def initialize(repo, tooling)
      @repo = repo
      @tooling = tooling
      @source = File.join(repo, 'docs/planning')
    end

    def run(args)
      command, mode, extra = args
      mode ||= 'development' if command == 'build'
      case command
      when '--help', '-h'
        puts USAGE
      when 'validate'
        raise Invalid, USAGE if mode
        records = load_records
        counts = Records::KINDS.map { |kind| "#{records.group(kind).size} #{kind}" }
        puts "Validated #{counts.join(', ')}."
      when 'build'
        raise Invalid, USAGE if extra || !Build::MODES.include?(mode)
        context = if mode == 'release'
                    "Source revision #{release_revision}"
                  else
                    "#{mode.capitalize} preview · Built #{build_date}"
                  end
        records = load_records
        output = File.join(@tooling, '.output')
        Build.new(output, @repo).write(records, mode: mode, context: context)
        puts "Built the planning workspace in #{File.join(output, mode, 'internal')}."
      else
        raise Invalid, USAGE
      end
      0
    rescue Invalid, SystemCallError => error
      warn error.message
      1
    end

    private

    def load_records
      records = Records.new(@source, repo: @repo)
      %w[activities requirements features milestones stories].each do |kind|
        if records.group(kind).empty?
          raise Invalid, "#{File.join(@source, kind)}: expected at least one record"
        end
      end
      records
    end

    def build_date
      epoch = ENV['SOURCE_DATE_EPOCH']
      time = epoch ? Time.at(Integer(epoch, 10)).utc : Time.now
      time.strftime('%Y-%m-%d')
    rescue ArgumentError, RangeError
      raise Invalid, 'SOURCE_DATE_EPOCH must be a Unix timestamp in seconds'
    end

    def release_revision
      args = %w[status --porcelain --untracked-files=all -- docs/planning tools/planning-report] + Build::ASSET_SOURCES.values
      status = git(*args)
      raise Invalid, "Release requires planning records, tooling, and brand assets from a known Git revision.\n#{status}" unless status.empty?

      git('rev-parse', 'HEAD').strip
    end

    def git(*args)
      stdout, stderr, result = Open3.capture3('git', '-C', @repo, *args)
      raise Invalid, "Could not determine the source revision: #{stderr.strip}" unless result.success?
      stdout
    end
  end
end

if $PROGRAM_NAME == __FILE__
  exit Planning::CLI.new(File.expand_path('../..', __dir__), __dir__).run(ARGV)
end
