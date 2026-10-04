/**
 * TravelHub safe console policy:
 * - suppress verbose logs by default;
 * - keep warn/error with lightweight sanitization;
 * - expose THSafeTrace() for backend `_trace` payloads.
 */
(function (w) {
  'use strict';
  if (!w || !w.console) return;

  var original = {
    log: w.console.log ? w.console.log.bind(w.console) : function () {},
    info: w.console.info ? w.console.info.bind(w.console) : function () {},
    debug: w.console.debug ? w.console.debug.bind(w.console) : function () {},
    warn: w.console.warn ? w.console.warn.bind(w.console) : function () {},
    error: w.console.error ? w.console.error.bind(w.console) : function () {}
  };

  function verboseEnabled() {
    try {
      if (w.__TH_VERBOSE_DEBUG === true) return true;
      return w.localStorage && w.localStorage.getItem('th_verbose_debug') === '1';
    } catch (e) {
      return false;
    }
  }

  function isSensitiveKey(key) {
    var k = String(key || '').toLowerCase();
    return /token|secret|password|authorization|cookie|jwt|phone|email|card|passport|message|reply|raw|response/.test(k);
  }

  function sanitize(val, depth) {
    depth = depth || 0;
    if (depth > 3) return '[depth-limit]';
    if (val == null) return val;
    if (typeof val === 'string') {
      if (val.length > 180) return val.slice(0, 180) + '…';
      if (/^\+?[0-9\-\s()]{7,}$/.test(val)) return '[phone]';
      if (val.indexOf('@') > 0) return '[email]';
      return val;
    }
    if (Array.isArray(val)) {
      return val.slice(0, 20).map(function (v) { return sanitize(v, depth + 1); });
    }
    if (typeof val === 'object') {
      var out = {};
      var keys = Object.keys(val).slice(0, 40);
      for (var i = 0; i < keys.length; i++) {
        var k = keys[i];
        out[k] = isSensitiveKey(k) ? '[redacted]' : sanitize(val[k], depth + 1);
      }
      return out;
    }
    return val;
  }

  w.console.log = function () {
    if (!verboseEnabled()) return;
    return original.log.apply(null, arguments);
  };
  w.console.info = function () {
    if (!verboseEnabled()) return;
    return original.info.apply(null, arguments);
  };
  w.console.debug = function () {
    if (!verboseEnabled()) return;
    return original.debug.apply(null, arguments);
  };
  w.console.warn = function () {
    var args = Array.prototype.slice.call(arguments).map(function (a) { return sanitize(a, 0); });
    return original.warn.apply(null, args);
  };
  w.console.error = function () {
    var args = Array.prototype.slice.call(arguments).map(function (a) { return sanitize(a, 0); });
    return original.error.apply(null, args);
  };

  w.THSafeTrace = function (responseJson) {
    try {
      if (!responseJson || !responseJson._trace) return;
      var t = sanitize(responseJson._trace, 0);
      original.info('[TH backend trace]', t);
    } catch (e) {
      // noop
    }
  };
})(typeof window !== 'undefined' ? window : this);

