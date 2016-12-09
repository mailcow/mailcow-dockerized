(function() {
  var SELECTOR, addEventListener, clickEvents, numberRegExp, sortable, touchDevice, trimRegExp;

  SELECTOR = 'table[data-sortable]';

  numberRegExp = /^-?[£$¤]?[\d,.]+%?$/;

  trimRegExp = /^\s+|\s+$/g;

  clickEvents = ['click'];

  touchDevice = 'ontouchstart' in document.documentElement;

  if (touchDevice) {
    clickEvents.push('touchstart');
  }

  addEventListener = function(el, event, handler) {
    if (el.addEventListener != null) {
      return el.addEventListener(event, handler, false);
    } else {
      return el.attachEvent("on" + event, handler);
    }
  };

  sortable = {
    init: function(options) {
      var table, tables, _i, _len, _results;
      if (options == null) {
        options = {};
      }
      if (options.selector == null) {
        options.selector = SELECTOR;
      }
      tables = document.querySelectorAll(options.selector);
      _results = [];
      for (_i = 0, _len = tables.length; _i < _len; _i++) {
        table = tables[_i];
        _results.push(sortable.initTable(table));
      }
      return _results;
    },
    initTable: function(table) {
      var i, th, ths, _i, _len, _ref;
      if (((_ref = table.tHead) != null ? _ref.rows.length : void 0) !== 1) {
        return;
      }
      if (table.getAttribute('data-sortable-initialized') === 'true') {
        return;
      }
      table.setAttribute('data-sortable-initialized', 'true');
      ths = table.querySelectorAll('th');
      for (i = _i = 0, _len = ths.length; _i < _len; i = ++_i) {
        th = ths[i];
        if (th.getAttribute('data-sortable') !== 'false') {
          sortable.setupClickableTH(table, th, i);
        }
      }
      return table;
    },
    setupClickableTH: function(table, th, i) {
      var eventName, onClick, type, _i, _len, _results;
      type = sortable.getColumnType(table, i);
      onClick = function(e) {
        var compare, item, newSortedDirection, position, row, rowArray, sorted, sortedDirection, tBody, ths, value, _compare, _i, _j, _k, _l, _len, _len1, _len2, _len3, _len4, _m, _ref, _ref1;
        if (e.handled !== true) {
          e.handled = true;
        } else {
          return false;
        }
        sorted = this.getAttribute('data-sorted') === 'true';
        sortedDirection = this.getAttribute('data-sorted-direction');
        if (sorted) {
          newSortedDirection = sortedDirection === 'ascending' ? 'descending' : 'ascending';
        } else {
          newSortedDirection = type.defaultSortDirection;
        }
        ths = this.parentNode.querySelectorAll('th');
        for (_i = 0, _len = ths.length; _i < _len; _i++) {
          th = ths[_i];
          th.setAttribute('data-sorted', 'false');
          th.removeAttribute('data-sorted-direction');
        }
        this.setAttribute('data-sorted', 'true');
        this.setAttribute('data-sorted-direction', newSortedDirection);
        tBody = table.tBodies[0];
        rowArray = [];
        if (!sorted) {
          if (type.compare != null) {
            _compare = type.compare;
          } else {
            _compare = function(a, b) {
              return b - a;
            };
          }
          compare = function(a, b) {
            if (a[0] === b[0]) {
              return a[2] - b[2];
            }
            if (type.reverse) {
              return _compare(b[0], a[0]);
            } else {
              return _compare(a[0], b[0]);
            }
          };
          _ref = tBody.rows;
          for (position = _j = 0, _len1 = _ref.length; _j < _len1; position = ++_j) {
            row = _ref[position];
            value = sortable.getNodeValue(row.cells[i]);
            if (type.comparator != null) {
              value = type.comparator(value);
            }
            rowArray.push([value, row, position]);
          }
          rowArray.sort(compare);
          for (_k = 0, _len2 = rowArray.length; _k < _len2; _k++) {
            row = rowArray[_k];
            tBody.appendChild(row[1]);
          }
        } else {
          _ref1 = tBody.rows;
          for (_l = 0, _len3 = _ref1.length; _l < _len3; _l++) {
            item = _ref1[_l];
            rowArray.push(item);
          }
          rowArray.reverse();
          for (_m = 0, _len4 = rowArray.length; _m < _len4; _m++) {
            row = rowArray[_m];
            tBody.appendChild(row);
          }
        }
        if (typeof window['CustomEvent'] === 'function') {
          return typeof table.dispatchEvent === "function" ? table.dispatchEvent(new CustomEvent('Sortable.sorted', {
            bubbles: true
          })) : void 0;
        }
      };
      _results = [];
      for (_i = 0, _len = clickEvents.length; _i < _len; _i++) {
        eventName = clickEvents[_i];
        _results.push(addEventListener(th, eventName, onClick));
      }
      return _results;
    },
    getColumnType: function(table, i) {
      var row, specified, text, type, _i, _j, _len, _len1, _ref, _ref1, _ref2;
      specified = (_ref = table.querySelectorAll('th')[i]) != null ? _ref.getAttribute('data-sortable-type') : void 0;
      if (specified != null) {
        return sortable.typesObject[specified];
      }
      _ref1 = table.tBodies[0].rows;
      for (_i = 0, _len = _ref1.length; _i < _len; _i++) {
        row = _ref1[_i];
        text = sortable.getNodeValue(row.cells[i]);
        _ref2 = sortable.types;
        for (_j = 0, _len1 = _ref2.length; _j < _len1; _j++) {
          type = _ref2[_j];
          if (type.match(text)) {
            return type;
          }
        }
      }
      return sortable.typesObject.alpha;
    },
    getNodeValue: function(node) {
      var dataValue;
      if (!node) {
        return '';
      }
      dataValue = node.getAttribute('data-value');
      if (dataValue !== null) {
        return dataValue;
      }
      if (typeof node.innerText !== 'undefined') {
        return node.innerText.replace(trimRegExp, '');
      }
      return node.textContent.replace(trimRegExp, '');
    },
    setupTypes: function(types) {
      var type, _i, _len, _results;
      sortable.types = types;
      sortable.typesObject = {};
      _results = [];
      for (_i = 0, _len = types.length; _i < _len; _i++) {
        type = types[_i];
        _results.push(sortable.typesObject[type.name] = type);
      }
      return _results;
    }
  };

  sortable.setupTypes([
    {
      name: 'numeric',
      defaultSortDirection: 'descending',
      match: function(a) {
        return a.match(numberRegExp);
      },
      comparator: function(a) {
        return parseFloat(a.replace(/[^0-9.-]/g, ''), 10) || 0;
      }
    }, {
      name: 'date',
      defaultSortDirection: 'ascending',
      reverse: true,
      match: function(a) {
        return !isNaN(Date.parse(a));
      },
      comparator: function(a) {
        return Date.parse(a) || 0;
      }
    }, {
      name: 'alpha',
      defaultSortDirection: 'ascending',
      match: function() {
        return true;
      },
      compare: function(a, b) {
        return a.localeCompare(b);
      }
    }
  ]);

  setTimeout(sortable.init, 0);

  if (typeof define === 'function' && define.amd) {
    define(function() {
      return sortable;
    });
  } else if (typeof exports !== 'undefined') {
    module.exports = sortable;
  } else {
    window.Sortable = sortable;
  }

}).call(this);
