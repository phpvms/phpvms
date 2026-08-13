<template>
  <button class="search pv-header-search" type="button" :aria-label="$t('ui.search')">
    <svg viewBox="0 0 24 24" class="i" fill="none" stroke="currentColor" stroke-width="1.8">
      <circle cx="11" cy="11" r="7" />
      <path d="m21 21-4.3-4.3" />
    </svg>
    <span class="ph">{{ $t("ui.search_placeholder") }}</span>
    <span class="kbd"><span>⌘</span><span>K</span></span>
  </button>
</template>

<style scoped>
@layer components {
  .search {
    flex: 1;
    max-width: 520px;
    height: 36px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 12px;
    border: 1px solid var(--pv-line);
    border-radius: var(--pv-radius-lg);
    background: var(--pv-panel-inset);
    color: var(--pv-ink-faint);
    cursor: pointer;
    font-size: 13px;
  }
  .search:hover {
    border-color: var(--pv-line-strong);
  }
  .search .i {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
  }
  .ph {
    flex: 1;
    text-align: left;
  }
  .kbd {
    display: flex;
    gap: 3px;
  }
  .kbd span {
    font-family: var(--pv-font-mono);
    font-size: 11px;
    line-height: 1;
    padding: 2px 5px;
    border: 1px solid var(--pv-line);
    border-radius: 4px;
    color: var(--pv-ink-dim);
  }

  @media (max-width: 640px) {
    .search {
      width: 44px;
      height: 44px;
      flex: 0 0 44px;
      justify-content: center;
      padding: 0;
      border: 0;
      background: transparent;
    }
    .ph,
    .kbd {
      display: none;
    }
  }
}
</style>
