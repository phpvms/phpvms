import { inject, type InjectionKey } from "vue";
import type { ThemeDocumentV1 } from "./schema";
import type { ResolvedTheme } from "./resolver";

export interface ThemeContext {
  document: ThemeDocumentV1;
  resolved: ResolvedTheme;
}

export const ThemeContextKey: InjectionKey<ThemeContext> = Symbol("pv.theme");

export function useThemeContext(): ThemeContext {
  const context = inject(ThemeContextKey);
  if (!context) throw new Error("phpVMS theme context is not available");
  return context;
}
