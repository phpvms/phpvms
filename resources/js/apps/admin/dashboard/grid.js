document.addEventListener("alpine:init", () => {
  window.Alpine.data("phpvmsDashboardGrid", (options = {}) => ({
    options: { templateColumns: 12, ...options },

    init() {
      this.$nextTick(() => this.bootGrids());
    },

    /**
     * GridStack instances are always read back off their element, never kept
     * in Alpine component state. Alpine deep-proxies its data, and a proxied
     * instance makes GridStack register its drag callbacks against the proxy:
     * the `node.grid === this` identity check at dragstop then fails, so a
     * dragged widget never gets its position restored and snaps to column 0.
     *
     * @returns {HTMLElement[]}
     */
    gridElements() {
      return Array.from(this.$el.querySelectorAll(".grid-stack"));
    },

    bootGrids() {
      this.gridElements().forEach((gridElement) => {
        const grid = window.GridStack.init(
          {
            column: Number.parseInt(gridElement.getAttribute("gs-column"), 10) || 12,
            cellHeight: Number.parseInt(gridElement.getAttribute("gs-cell-height"), 10) || 100,
            handle: ".dd-drag-handle",
            staticGrid: true,
            // 4px inset per side → 8px between widgets. The stats cell needs
            // an 85.8px content box for its three lines at the strip's type
            // sizes; a 100px row leaves 90px at this inset, so it fits with
            // the original 12/14/11 padding intact.
            margin: 4,
            animate: true,
            float: true,
          },
          gridElement,
        );

        grid.on("resizestop", () => window.dispatchEvent(new Event("resize")));
      });

      // The lazily-imported dashboard chunk can resolve before Alpine boots, so
      // its edit-mode restore waits on this rather than assuming a live grid.
      window.dispatchEvent(new CustomEvent("dashboard-grid:ready"));
    },

    setEditable(editable) {
      this.$el.classList.toggle("is-readonly", !editable);
      this.gridElements().forEach((gridElement) => gridElement.gridstack?.setStatic(!editable));
    },

    destroy() {
      this.gridElements().forEach((gridElement) => gridElement.gridstack?.destroy(false));
    },
  }));
});
