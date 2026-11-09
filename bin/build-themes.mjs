/**
 * Builds every bundled theme addon that has a build step.
 *
 * A theme addon opts in by listing a `build:theme` script in its own
 * `package.json` under `modules/<name>/` (and being a member of the pnpm
 * workspace, see pnpm-workspace.yaml). Symlinked modules (dev checkouts of
 * other repos) are skipped: they are built by their own repo.
 *
 * Usage: node bin/build-themes.mjs
 */
import { existsSync, lstatSync, readdirSync, readFileSync } from "node:fs";
import { spawnSync } from "node:child_process";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const modulesDir = join(repoRoot, "modules");

const themes = readdirSync(modulesDir)
  .map((name) => join(modulesDir, name))
  .filter((dir) => !lstatSync(dir).isSymbolicLink() && existsSync(join(dir, "package.json")))
  .filter(
    (dir) =>
      "build:theme" in (JSON.parse(readFileSync(join(dir, "package.json"), "utf8")).scripts ?? {}),
  );

for (const dir of themes) {
  console.log(`Building theme ${dir.replace(`${repoRoot}/`, "")}`);

  const result = spawnSync("pnpm", ["--dir", dir, "run", "build:theme"], { stdio: "inherit" });

  if (result.status !== 0) {
    process.exit(result.status ?? 1);
  }
}

if (themes.length === 0) {
  console.log("No theme addon with a build:theme script.");
}
