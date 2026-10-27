/**
 * One entry of the site menu, as `NavigationService::menu()` shares it in the
 * `navigation` Inertia prop: already ordered, filtered for the viewer, and with
 * its label translated. Admins manage it under Content → Menu Items.
 */
export interface NavigationMenuItem {
  label: string;
  /** Absolute URL; empty for an item that only groups its children. */
  url: string;
  icon: string | null;
  new_window: boolean;
  /** Route and page targets are in-app visits; URL targets are plain links. */
  spa: boolean;
  children: NavigationMenuItem[];
}

/** The path part of a menu URL, for matching it against the current page. */
export function menuPath(url: string): string | null {
  if (url === "") {
    return null;
  }

  try {
    const parsed = new URL(url, window.location.origin);

    return parsed.origin === window.location.origin
      ? parsed.pathname.replace(/\/$/, "") || "/"
      : null;
  } catch {
    return null;
  }
}

/**
 * The menu path the current page sits under: the longest one that equals the
 * page path or is a prefix of it, so `/flights/bids` beats `/flights`.
 */
export function activeMenuPath(
  items: readonly NavigationMenuItem[],
  pagePath: string,
): string | null {
  let best: string | null = null;

  const visit = (entries: readonly NavigationMenuItem[]): void => {
    for (const entry of entries) {
      const path = menuPath(entry.url);

      if (
        path !== null &&
        (pagePath === path || pagePath.startsWith(`${path}/`)) &&
        (best === null || path.length > best.length)
      ) {
        best = path;
      }

      visit(entry.children);
    }
  };

  visit(items);

  return best;
}
