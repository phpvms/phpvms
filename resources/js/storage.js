/**
 * Before you edit these, read the documentation on how these files are compiled:
 * https://docs.phpvms.net/developers/building-assets
 *
 * Edits here don't take place until you compile these assets and then upload them.
 */

/**
 * Simple browser storage interface
 */
export default class Storage {
  constructor(name, default_value) {
    this.name = name;

    // Read the object from storage; if it doesn't exist, set
    // it to the default value
    const st = window.localStorage.getItem(this.name);
    if (!st) {
      console.log("Nothing found in storage, starting from default");
      this.data = default_value;
    } else {
      console.log("Found in storage: ", st);
      this.data = JSON.parse(st);
    }
  }

  /**
   * Save to local storage
   */
  save() {
    window.localStorage.setItem(this.name, JSON.stringify(this.data));
  }

  /**
   * Return a list from a given key
   *
   * @param {String} key
   *
   * @returns {Array|*}
   */
  getList(key) {
    if (!(key in this.data)) {
      return [];
    }

    return this.data[key];
  }

  /**
   * Add `value` to a given `key`
   *
   * @param {string} key
   * @param {*} value
   */
  addToList(key, value) {
    if (!(key in this.data)) {
      this.data[key] = [];
    }

    const index = this.data[key].indexOf(value);
    if (index === -1) {
      this.data[key].push(value);
    }
  }

  /**
   * Remove `value` from the given `key`
   *
   * @param {String} key
   * @param {*} value
   */
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
