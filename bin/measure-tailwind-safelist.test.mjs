/**
 * Verifies the tailwind-safelist size budget guard itself (D5): the real
 * safelist stays within budget, a family added on top of it pushes past the
 * budget (proving the guard would actually catch a regression), and the
 * measurement is isolated from unrelated scanned content on disk (proving
 * `source(none)` does what it says).
 *
 * Each case compiles real CSS through the real `@tailwindcss/vite` plugin —
 * no mocking of Tailwind's output — so a slow suite (several seconds) is
 * expected and correct for what it is asserting.
 */
import { describe, expect, it } from "vitest";
import {
  BUDGET_BROTLI_BYTES,
  measureCompiledCss,
  measureSafelist,
} from "./measure-tailwind-safelist.mjs";

describe("tailwind-safelist size budget", () => {
  it("compiles within the recorded brotli budget", async () => {
    const { brotli } = await measureSafelist();

    expect(brotli).toBeGreaterThan(0);
    expect(brotli).toBeLessThanOrEqual(BUDGET_BROTLI_BYTES);
  }, 30_000);

  it("fails the budget once an unbudgeted family is added", async () => {
    const oversized = `
      @import "tailwindcss" source(none);
      @source inline("{sm:,md:,lg:,xl:,2xl:,hover:,focus:,dark:,}{text,bg,border}-{red,orange,amber,yellow,lime,green,emerald,teal,cyan,sky,blue,indigo,violet,purple,fuchsia,pink,rose,slate,gray,zinc,neutral,stone}-{50,{100..900..100},950}");
      @source inline("{sm:,md:,lg:,xl:,2xl:,hover:,focus:,dark:,}{p,px,py,pt,pr,pb,pl,m,mx,my,mt,mr,mb,ml,gap,w,h}-{0,px,0.5,1,1.5,2,2.5,3,3.5,4,5,6,7,8,9,10,11,12,14,16,20,24,28,32,36,40,44,48,52,56,60,64,72,80,96}");
      /* Extra, unbudgeted family: decoration/outline utilities, not part of D3. */
      @source inline("{sm:,md:,lg:,xl:,2xl:,hover:,focus:,dark:,}decoration-{red,orange,amber,yellow,lime,green,emerald,teal,cyan,sky,blue,indigo,violet,purple,fuchsia,pink,rose,slate,gray,zinc,neutral,stone}-{50,{100..900..100},950}");
      @source inline("{sm:,md:,lg:,xl:,2xl:,hover:,focus:,dark:,}outline-{red,orange,amber,yellow,lime,green,emerald,teal,cyan,sky,blue,indigo,violet,purple,fuchsia,pink,rose,slate,gray,zinc,neutral,stone}-{50,{100..900..100},950}");
    `;

    const { brotli } = await measureCompiledCss(oversized);

    expect(brotli).toBeGreaterThan(BUDGET_BROTLI_BYTES);
  }, 30_000);

  it("is unaffected by unrelated scanned utilities elsewhere on disk", async () => {
    const css = `
      @import "tailwindcss" source(none);
      @source inline("bg-red-500 p-4 flex");
    `;

    const isolated = await measureCompiledCss(css);
    const withNoise = await measureCompiledCss(css, {
      extraFiles: {
        // Not referenced by any @source directive — source(none) means this
        // file is never scanned. If it moved the number, isolation would be
        // broken.
        "unrelated-view.blade.php": `<div class="mt-[123px] bg-[#010203] unrelated-made-up-class"></div>`,
      },
    });

    expect(withNoise.raw).toBe(isolated.raw);
    expect(withNoise.brotli).toBe(isolated.brotli);
  }, 30_000);
});
