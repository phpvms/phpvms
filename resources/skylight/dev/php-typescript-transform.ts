import { spawn } from "node:child_process";
import { existsSync, readdirSync } from "node:fs";
import { resolve } from "node:path";
import type { Plugin } from "vite";

/**
 * Dev-only Vite plugin: regenerate the spatie/typescript-transformer output
 * (`resources/<theme>/apps/spa/types/generated.d.ts`) whenever a PHP DTO changes,
 * so the SPA's generated types stay in sync while the dev server is running.
 *
 * `composer dev` runs vite in the same process group as `php artisan serve`, so
 * `php artisan …` is on PATH here. Override the command via env TS_TRANSFORM_CMD
 * to route through a container, e.g.:
 *   TS_TRANSFORM_CMD="docker exec skylight-laravel.test-1 php artisan typescript:transform"
 *
 * Watches the same directories the transformer scans (keep in sync with
 * app/Providers/TypeScriptTransformerServiceProvider::configure). No-op on build
 * (`apply: 'serve'`).
 */
export function phpTypescriptTransform(opts: { repoRoot: string }): Plugin {
  const cmd = process.env.TS_TRANSFORM_CMD ?? "php artisan typescript:transform";
  const dtoDirs = [
    "app/Http/Data",
    "app/Support/Dto/PhpvmsApi",
    ...moduleDataDirs(opts.repoRoot),
  ].map((d) => resolve(opts.repoRoot, d));

  let timer: ReturnType<typeof setTimeout> | null = null;
  let running = false;
  let queued = false;

  const run = (): void => {
    if (running) {
      queued = true;
      return;
    }
    running = true;
    console.log("[ts-transform] PHP DTO changed → regenerating generated.d.ts");
    const child = spawn(cmd, { cwd: opts.repoRoot, stdio: "inherit", shell: true });
    child.on("exit", () => {
      running = false;
      if (queued) {
        queued = false;
        run();
      }
    });
  };

  const trigger = (): void => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(run, 300);
  };

  return {
    name: "php-typescript-transform",
    apply: "serve",
    configureServer(server) {
      for (const dir of dtoDirs) {
        if (existsSync(dir)) server.watcher.add(dir);
      }
      const onChange = (file: string): void => {
        if (file.endsWith(".php") && dtoDirs.some((d) => file.startsWith(d))) trigger();
      };
      server.watcher.on("add", onChange);
      server.watcher.on("change", onChange);
      server.watcher.on("unlink", onChange);
    },
  };
}

/** modules/<Name>/Http/Data for every module (mirrors the transformer scan). */
function moduleDataDirs(repoRoot: string): string[] {
  const modules = resolve(repoRoot, "modules");
  if (!existsSync(modules)) return [];
  return readdirSync(modules).map((m) => `modules/${m}/Http/Data`);
}
