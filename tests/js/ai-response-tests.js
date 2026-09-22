const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");

const source = fs.readFileSync(path.join(__dirname, "../../public/assets/js/ai.js"), "utf8");
const window = { Mattrics: {} };
vm.runInNewContext(source, { window });

function response(status, contentType, payload) {
  return {
    ok: status >= 200 && status < 300,
    status,
    headers: { get: () => contentType },
    json: async () => payload,
  };
}

async function main() {
  const read = window.Mattrics.readAiResponse;
  assert.equal(await read(response(200, "application/json; charset=utf-8", { text: "Workout" })), "Workout");
  assert.equal(await read(response(200, "application/json", { text: "" })), "No response.");
  await assert.rejects(read(response(502, "application/json", { error: "Upstream service unavailable." })),
    /Upstream service unavailable/);
  await assert.rejects(read(response(502, "text\/html", null)), /AI service unavailable \(HTTP 502\)/);
  await assert.rejects(read(response(200, "text\/html", null)), /unexpected response/);
  await assert.rejects(read({ ...response(502, "application/json", null), json: async () => { throw new SyntaxError("HTML"); } }),
    /AI service unavailable \(HTTP 502\)/);
  console.log("ai-response-tests: 6 passed, 0 failed");
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
