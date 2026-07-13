const { JSDOM } = require('jsdom');
const dom = new JSDOM('<!DOCTYPE html><html><body></body></html>');
global.window = dom.window;
global.document = dom.window.document;
global.Element = dom.window.Element;
global.HTMLElement = dom.window.HTMLElement;
global.Node = dom.window.Node;
global.getComputedStyle = dom.window.getComputedStyle;

// simulate Moodle's Tooltip / Popover behavior
class BaseComponent {
  constructor(element) {
    if (!element) return;
    this._element = element;
  }
}
class Tooltip extends BaseComponent {
  static get Default() { return { trigger: 'hover focus' }; }
  constructor(element, config) {
    super(element);
    this._config = this._getConfig(config);
    this._setListeners();
  }
  _getConfig(config) {
    let dataAttr = {};
    if (!this._element) dataAttr = {}; 
    else dataAttr = { trigger: 'click' }; // simulate data-bs-trigger
    
    // BUT what if this._element is null, and manipulating dataset throws?
    // In Moodle's actual code, Manipulator.getDataAttributes might throw!
    
    return { ...this.constructor.Default, ...dataAttr, ...(config || {}) };
  }
  _setListeners() {
    const triggers = this._config.trigger.split(' ');
  }
}
class Popover extends Tooltip {
  static get Default() { return { trigger: 'click' }; }
}

try {
  new Popover(null, { container: 'body' });
  console.log("No error with null element");
} catch(e) {
  console.log("Error:", e.message);
}
