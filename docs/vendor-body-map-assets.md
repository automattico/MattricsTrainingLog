# Vendored Body Map Asset

The fatigue heat map uses vendored SVG path data derived from:

- `@teambuildr/react-native-body-highlighter` `v3.0.7`
- Source: <https://www.npmjs.com/package/@teambuildr/react-native-body-highlighter>
- Upstream repository lineage: `react-native-body-highlighter`
- License: MIT

Only the male front/back outline and the fatigue-relevant muscle path groups were ported into:

- `public/assets/js/body-map-team-buildr.js`

This repo does not ship the React Native runtime from the package. It only vendors adapted SVG path data for inline browser rendering.
