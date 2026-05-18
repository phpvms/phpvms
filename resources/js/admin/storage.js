/**
 * Simple localStorage wrapper.
 * Mirrors resources/js/storage.js.
 */

export default class Storage {
  constructor(name, default_value) {
    this.name = name;

    const st = window.localStorage.getItem(this.name);
    if (!st) {
      this.data = default_value;
    } else {
      this.data = JSON.parse(st);
    }
  }

  save() {
    window.localStorage.setItem(this.name, JSON.stringify(this.data));
  }

  getList(key) {
    if (!(key in this.data)) {
      return [];
    }

    return this.data[key];
  }

  addToList(key, value) {
    if (!(key in this.data)) {
      this.data[key] = [];
    }

    const index = this.data[key].indexOf(value);
    if (index === -1) {
      this.data[key].push(value);
    }
  }

  removeFromList(key, value) {
    if (!(key in this.data)) {
      return;
    }

    const index = this.data[key].indexOf(value);
    if (index !== -1) {
      this.data[key].splice(index, 1);
    }
  }
}
