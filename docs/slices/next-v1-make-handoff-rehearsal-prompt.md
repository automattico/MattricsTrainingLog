# Rehearse Make delivery and its checkpoint

Read AGENTS.md, docs/architecture-next.md, docs/codex-framework.md, docs/implementation-progress.md and the v1.0 planning records. Use the [fresh parity report](v1-fresh-dashboard-parity-report.md) as evidence.

Inspect the signed-in Make scenario read-only. Confirm the trigger, listing bounds, Sheet deduplication, write route and checkpoint formula. In disposable local Postgres, replay an older rename and trim, a late arrival, a failed batch and an ambiguous acknowledgement. Verify the retained row identity, checkpoint visibility after commit, expiry switch and canonical projection. Document a production retention proposal and the remaining end-to-end writer and cleanup work. Keep Make and Sheets live and avoid using private recordings in synthetic tests.

Run the repository, Postgres and planning gates. Update the backlog, implementation progress, slice report and next prompt. Hosted writes, scenario changes, deployment and production schedules require explicit approval.
