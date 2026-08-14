// Alpine component that wires every `.grid-stack` inside `.dashboard-canvas`
// to a GridStack instance. Drag, resize, and cross-section moves all flush
// the full layout back to the Livewire page via `persistLayout`.

document.addEventListener('alpine:init', () => {
    Alpine.data('dashboardGrid', (options = {}) => ({
        options: { editable: true, templateColumns: 12, ...options },
        grids: [],
        debounceTimer: null,

        init() {
            this.$nextTick(() => this.bootGrids());

            // Live updates from the manager's Locked / template toggles.
            // `dashboard-list-changed` causes the page to dispatch this with
            // the new effective editable state — we flip every GridStack's
            // staticGrid mode and toggle the `.is-readonly` class on the
            // canvas (which hides the action chrome via CSS).
            if (window.Livewire) {
                window.Livewire.on('dynamic-dashboard:editable-changed', (payload) => {
                    const data = Array.isArray(payload) ? payload[0] : payload;
                    this.setEditable(Boolean(data?.editable));
                });
            }
        },

        setEditable(editable) {
            this.options.editable = editable;
            this.$el.classList.toggle('is-readonly', ! editable);
            this.grids.forEach((grid) => grid.setStatic(! editable));
        },

        bootGrids() {
            const editable = this.options.editable;

            this.$el.querySelectorAll('.grid-stack').forEach((el) => {
                const grid = GridStack.init(
                    {
                        column: parseInt(el.getAttribute('gs-column')) || 12,
                        cellHeight: parseInt(el.getAttribute('gs-cell-height')) || 80,
                        acceptWidgets: editable,
                        handle: '.dd-drag-handle',
                        staticGrid: !editable,
                        // 12px on each side of every item → 24px gutter between
                        // widgets, enough room for the floating title badge
                        // (which sticks up 0.75rem / 12px) to clear the widget
                        // above without overlapping.
                        margin: 12,
                        animate: true,
                    },
                    el
                );

                if (editable) {
                    grid.on('change added removed', () => this.scheduleFlush());

                    // Widget content (charts) follows the new box size: most chart
                    // libraries auto-resize on window.resize, and widgets that need
                    // explicit handling can listen to the Livewire event below.
                    grid.on('resizestop', (event, item) => this.broadcastResize(item));

                    // Drop-zone highlight: drive a canvas-level class from
                    // dragstart on every grid; clear it on whichever event
                    // signals the drag ended.
                    grid.on('dragstart', () => {
                        this.$el.classList.add('is-dragging');
                        el.classList.add('is-drag-source');
                    });

                    // dragstop fires for within-grid drops. dropped fires on the
                    // DESTINATION grid for cross-section drops — and dragstop
                    // may NOT fire on the source in that case because the
                    // dragged element has been re-parented away. Listening to
                    // both covers every scenario.
                    grid.on('dragstop', (event, item) => this.endDrag(item));
                    grid.on('dropped', (event, _prev, newWidget) => this.endDrag(newWidget?.el));
                }

                this.grids.push(grid);
            });
        },

        broadcastResize(item) {
            // Universal: ApexCharts, Chart.js, ECharts, Plotly auto-resize on this.
            window.dispatchEvent(new Event('resize'));

            // Targeted: widgets opt-in via #[On('dynamic-dashboard:widget-resized')].
            const id = parseInt(item?.getAttribute?.('gs-id'));
            if (id && window.Livewire) {
                window.Livewire.dispatch('dynamic-dashboard:widget-resized', { id });
            }
        },

        endDrag(item) {
            // Clear our own drag-state classes AND defensively clear any
            // stuck GridStack `.grid-stack-dragging` class. Cross-section
            // drops can leave the source grid with a stale class because
            // dragstop never fires on it (the widget element has been
            // moved to the destination grid).
            this.$el.classList.remove('is-dragging');
            this.$el.querySelectorAll('.is-drag-source, .grid-stack-dragging')
                .forEach((g) => g.classList.remove('is-drag-source', 'grid-stack-dragging'));

            this.broadcastResize(item);
        },

        scheduleFlush() {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => this.flush(), 150);
        },

        flush() {
            const layout = [];

            this.$el.querySelectorAll('.grid-stack').forEach((gridEl) => {
                const section = gridEl.dataset.section;
                const grid = gridEl.gridstack;
                if (!grid) return;

                grid.engine.nodes.forEach((node) => {
                    const id = parseInt(node.el.getAttribute('gs-id'));
                    if (!id) return;

                    layout.push({
                        id,
                        section,
                        x: node.x,
                        y: node.y,
                        w: node.w,
                        h: node.h,
                    });
                });
            });

            this.$wire.call('persistLayout', layout);
        },
    }));
});
