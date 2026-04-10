(function () {
  const M = window.Mattrics;
  M.docsSections = M.docsSections || [];
  M.docsSections.push({
    id: "docs-ai-model",
    label: "AI model / coach",
    title: "Claude-powered workout recommendations",
    intro: "The app uses Anthropic's Claude model to generate personalized workout recommendations based on recent activity and current fatigue state.",
    body: `
      ${M.docsTable([
        ["Model", "Anthropic Claude 3.5 Sonnet (latest available). Refer to <code class=\"docs-code\">public/api/ai.php</code> for the current model ID."],
        ["Context window", "Sufficient to handle activity history, fatigue state, and user profile within a single request."],
        ["Temperature", "<code class=\"docs-code\">0.7</code> for balanced creativity and consistency in recommendations."],
        ["Max tokens", "<code class=\"docs-code\">1024</code> per response. Enough for a detailed workout plan without excessive length."],
        ["Streaming", "Responses are streamed server-side and sent to the client as server-sent events (SSE) for real-time display."],
      ])}

      ${M.docsSubsection("Prompt construction", `
        <p class="docs-copy">
          The coach mode builds a prompt that includes:
        </p>
        ${M.docsList([
          "User profile (age, bodyweight, experience level, recovery preferences)",
          "Recent activity summary (last 10 days of workouts, by type and duration)",
          "Current fatigue map (per-muscle readiness state and recovery hours remaining)",
          "Available time (user-specified session duration)",
          "Training goal or focus area (if provided)",
        ])}
        <p class="docs-copy">
          The system prompt instructs Claude to recommend a specific workout that respects the current fatigue state,
          aligns with the user's experience level, and fits within the available time. Recommendations include exercise
          selection, sets, reps or duration, and estimated intensity.
        </p>
      `)}

      ${M.docsSubsection("API boundary and security", `
        <p class="docs-copy">
          The client sends a fetch request to <code class="docs-code">public/api/ai.php</code> with the required context.
          The server validates the session, constructs the full prompt, calls the Anthropic API, and streams the response
          back to the client. The API key never leaves the server. Rate limiting and usage quotas should be monitored.
        </p>
      `)}

      ${M.docsSubsection("Response handling", `
        <p class="docs-copy">
          The client listens to server-sent events and renders the response as it arrives. If the stream is interrupted
          or an error occurs, the client displays a fallback message and suggests retrying. Users can abort an in-flight
          request and clear the coach pane.
        </p>
      `)}

      ${M.docsSubsection("Limitations and disclaimers", `
        <p class="docs-copy">
          The AI recommendations are generated from the fatigue model and recent activity data. They are suggestions, not
          medical advice. Users should consider their own intuition, injury history, and wellness to make final decisions.
          The model may occasionally recommend exercises that are not in the explicit pattern-matching list, in which case
          the user can manually log them or map them to similar exercises.
        </p>
      `)}
    `,
  });
}());
