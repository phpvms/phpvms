/**
 * Alpine is not bundled: Livewire (and TallStackUI's script) provide it as a
 * global. Only what airside's own code touches is typed here.
 */

interface AlpineGlobal {
  data<T extends object>(name: string, callback: (...args: any[]) => T & ThisType<T>): void;
  magic(name: string, callback: () => unknown): void;
  store(name: string): unknown;
}

interface Window {
  Alpine: AlpineGlobal;
}
