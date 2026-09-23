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
        ["Model", "The Anthropic model is configured server-side in <code class=\"docs-code\">private/config.php</code>."],
        ["Context", "Recent activity and the current muscle-fatigue estimate are sent to the server in one request."],
        ["Temperature", "No explicit temperature is set; the provider default applies."],
        ["Max tokens", "<code class=\"docs-code\">900</code> per response."],
        ["Response", "The server returns one JSON response after the provider call completes; streaming/SSE is not implemented."],
      ])}

      ${M.docsSubsection("Prompt construction", `
        <p class="docs-copy">
          The coach mode builds a prompt that includes:
        </p>
        ${M.docsList([
          "Recent activity summary (last 10 days of workouts, by type and duration)",
          "Current fatigue summary and per-region fatigue score, tier, and recovery labels",
          "A request for one concrete workout with a shoulder note when relevant",
        ])}
        <p class="docs-copy">
          The server prompt asks Claude for one brief, specific workout that respects current fatigue. It asks for
          exercises with sets/reps/weights or cardio distance/duration/intensity as appropriate. User settings,
          a chosen time budget, and a separate training goal are not currently included in this request.
        </p>
      `)}

      ${M.docsSubsection("API boundary and security", `
        <p class="docs-copy">
          The client sends <code class="docs-code">POST /api/ai</code> with the Mattwarden CSRF token. The endpoint calls
          <code class="docs-code">require_authenticated()</code> and the same-origin and CSRF guards, constructs the prompt,
          calls Anthropic server-side, and returns JSON. The API key never leaves protected server config.
        </p>
      `)}

      ${M.docsSubsection("Response handling", `
        <p class="docs-copy">
          The client displays the returned text when the request succeeds. On an error or a non-JSON upstream page,
          it shows a service-unavailable message rather than a raw JSON parser exception. The request is not streamed
          and the UI has no abort control.
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
