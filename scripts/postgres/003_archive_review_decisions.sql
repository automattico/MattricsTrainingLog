ALTER TABLE mattrics.mattrics_activity_source_links
    DROP CONSTRAINT mattrics_activity_source_links_link_kind_check,
    DROP CONSTRAINT mattrics_activity_source_links_check,
    ADD CHECK (link_kind IN ('primary', 'exact_fit', 'manual')),
    ADD CHECK ((link_kind <> 'primary') = (match_evidence IS NOT NULL));

ALTER TABLE mattrics.mattrics_archive_match_review
    DROP CONSTRAINT mattrics_archive_match_review_decision_check,
    ADD CHECK (decision IN ('keep_separate', 'link', 'split')),
    ADD COLUMN original_strava_activity_id uuid,
    ADD CHECK ((decision IS NOT DISTINCT FROM 'link') = (original_strava_activity_id IS NOT NULL));
