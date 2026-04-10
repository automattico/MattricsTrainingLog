<div class="detail-overlay" id="detailOverlay" onclick="closeDetail(event)">
  <div class="detail-modal" role="dialog" aria-modal="true" aria-labelledby="detailTitle" onclick="event.stopPropagation()">
    <div class="detail-head">
      <div>
        <div class="detail-kicker" id="detailKicker"></div>
        <div class="detail-title" id="detailTitle"></div>
        <div class="detail-date" id="detailDate"></div>
      </div>
      <button class="detail-close" onclick="closeDetail()" aria-label="Close session detail">×</button>
    </div>
    <div class="detail-body">
      <div class="detail-meta" id="detailMeta"></div>
      <div class="detail-section" id="detailNotesSection" style="display:none">
        <div class="detail-label">Notes</div>
        <div class="detail-note" id="detailNotes"></div>
      </div>
      <div class="detail-metrics" id="detailMetrics"></div>
      <div class="detail-section" id="detailWorkoutSection" style="display:none">
        <div class="detail-label">Workout breakdown</div>
        <div class="hevy-list" id="detailWorkoutList"></div>
      </div>
    </div>
  </div>
</div>
