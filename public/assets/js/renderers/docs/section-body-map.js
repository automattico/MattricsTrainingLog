(function () {
  const M = window.Mattrics;
  M.docsSections = M.docsSections || [];
  M.docsSections.push({
    id: "docs-body-map",
    label: "Body map assets",
    title: "Where the body figure comes from",
    intro: "The body map is vendored SVG path data, not a runtime dependency on the upstream React Native package.",
    body: `
      ${M.docsTable([
        ["Source", "<code class=\"docs-code\">@teambuildr/react-native-body-highlighter</code> v3.0.7."],
        ["What is shipped", "Only the male front/back outline and the fatigue-relevant muscle path groups."],
        ["Where it lives", "<code class=\"docs-code\">public/assets/js/body-map-team-buildr.js</code>."],
        ["Why it is vendored", "So the browser can render inline SVG without bundling the upstream React Native runtime."],
      ])}
    `,
  });
}());
