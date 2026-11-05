/**
 * The dotted look of `<x-map.svg pattern="dots">`: whole dots on a grid over
 * the land. The server sends the land paths (hidden, in `[data-map-land]`) and
 * the grid gap for the shown viewBox; this lays the grid over the viewBox,
 * keeps the points inside a country (canvas `isPointInPath` on the same path
 * data, even-odd so holes stay empty) and writes each set of dots as one path
 * of zero-length segments, drawn by round line caps.
 *
 * Rows and columns sit on multiples of the gap, so the grid does not shift
 * with the view; the diagonal grid offsets every other row by half a gap.
 */
export function mapDots({ gap, grid = "square" }: { gap: number; grid?: "square" | "diagonal" }) {
  return {
    init(this: { $el: Element }) {
      const svg = this.$el as SVGSVGElement;
      const box = svg.viewBox.baseVal;
      const ctx = document.createElement("canvas").getContext("2d")!;
      const countries = [...svg.querySelectorAll("[data-map-land] path")].map((path) => ({
        shape: new Path2D(path.getAttribute("d") ?? ""),
        focus: path.classList.contains("land--focus"),
      }));
      // The focus country first, so a dot on a shared border goes to it.
      countries.sort((a, b) => Number(b.focus) - Number(a.focus));

      const dots: Record<"land" | "focus", string[]> = { land: [], focus: [] };
      const firstRow = Math.floor(box.y / gap);
      const lastRow = Math.ceil((box.y + box.height) / gap);

      for (let row = firstRow; row <= lastRow; row++) {
        const y = (row + 0.5) * gap;
        const offset = grid === "diagonal" && row % 2 !== 0 ? gap / 2 : 0;
        const firstColumn = Math.floor((box.x - offset) / gap);
        const lastColumn = Math.ceil((box.x + box.width - offset) / gap);

        for (let column = firstColumn; column <= lastColumn; column++) {
          const x = (column + 0.5) * gap + offset;
          const country = countries.find((c) => ctx.isPointInPath(c.shape, x, y, "evenodd"));

          if (country) {
            dots[country.focus ? "focus" : "land"].push(`M${x.toFixed(2)} ${y.toFixed(2)}h0`);
          }
        }
      }

      for (const [set, cells] of Object.entries(dots)) {
        svg.querySelector(`[data-map-dots="${set}"]`)?.setAttribute("d", cells.join(""));
      }
    },
  };
}
