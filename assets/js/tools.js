(function () {
  "use strict";

  const enc = new TextEncoder();
  const dec = new TextDecoder();

  function $(sel, root) {
    return (root || document).querySelector(sel);
  }
  function $all(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }
  function el(tag, attrs, children) {
    const node = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        if (k === "class") node.className = attrs[k];
        else if (k === "text") node.textContent = attrs[k];
        else if (k === "html") node.innerHTML = attrs[k];
        else if (k.slice(0, 2) === "on") node.addEventListener(k.slice(2), attrs[k]);
        else if (attrs[k] === true) node.setAttribute(k, "");
        else if (attrs[k] !== false && attrs[k] != null) node.setAttribute(k, String(attrs[k]));
      });
    }
    (children || []).forEach(function (c) {
      if (c) node.appendChild(typeof c === "string" ? document.createTextNode(c) : c);
    });
    return node;
  }
  function labelWrap(text, control, hint) {
    const wrap = el("div", { class: "field" });
    const lab = el("label", { text: text });
    if (control.id) lab.setAttribute("for", control.id);
    wrap.appendChild(lab);
    wrap.appendChild(control);
    if (hint) wrap.appendChild(el("p", { class: "hint", text: hint }));
    return wrap;
  }
  function textarea(id, placeholder, extraClass) {
    return el("textarea", {
      id: id,
      class: "tool-in" + (extraClass ? " " + extraClass : ""),
      placeholder: placeholder || "",
      spellcheck: "false",
      autocapitalize: "off"
    });
  }
  function input(id, type, placeholder, value) {
    const n = el("input", {
      id: id,
      type: type || "text",
      placeholder: placeholder || ""
    });
    if (value != null) n.value = value;
    return n;
  }
  function btn(label, className, onClick) {
    return el("button", { type: "button", class: className || "", text: label, onclick: onClick });
  }
  function row(children) {
    const r = el("div", { class: "tool-row" });
    children.forEach(function (c) { r.appendChild(c); });
    return r;
  }
  function pre() {
    return el("pre", { class: "tool-out", tabindex: "0" });
  }
  function statusLine() {
    return el("p", { class: "tool-status", role: "status" });
  }
  function setStatus(node, text, kind) {
    node.textContent = text || "";
    node.className = "tool-status" + (kind ? " " + kind : "");
  }
  function setText(node, text) {
    node.textContent = text == null ? "" : String(text);
  }
  async function copyOut(text, button) {
    const value = typeof text === "function" ? text() : text;
    try {
      await navigator.clipboard.writeText(value || "");
      const prev = button.textContent;
      button.textContent = "Copied";
      setTimeout(function () { button.textContent = prev; }, 1200);
    } catch (e) {
      button.textContent = "Copy failed";
    }
  }
  function bytesOf(str) {
    return enc.encode(str);
  }
  function fromBytes(bytes) {
    return dec.decode(bytes);
  }
  function toHex(buf) {
    const u8 = buf instanceof Uint8Array ? buf : new Uint8Array(buf);
    let s = "";
    for (let i = 0; i < u8.length; i++) s += (u8[i] + 256).toString(16).slice(1);
    return s;
  }
  function fromHex(str) {
    const clean = String(str).replace(/\s+/g, "").replace(/^0x/i, "");
    if (clean.length % 2) throw new Error("Hex length must be even.");
    if (!/^[0-9a-f]*$/i.test(clean)) throw new Error("Not hex.");
    const out = new Uint8Array(clean.length / 2);
    for (let i = 0; i < out.length; i++) out[i] = parseInt(clean.substr(i * 2, 2), 16);
    return out;
  }
  function b64Encode(bytes, urlSafe) {
    let bin = "";
    const u8 = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
    for (let i = 0; i < u8.length; i++) bin += String.fromCharCode(u8[i]);
    let b64 = btoa(bin);
    if (urlSafe) b64 = b64.replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, "");
    return b64;
  }
  function b64Decode(str) {
    let s = String(str).replace(/\s+/g, "");
    s = s.replace(/-/g, "+").replace(/_/g, "/");
    while (s.length % 4) s += "=";
    const bin = atob(s);
    const out = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
    return out;
  }
  function b64urlDecode(str) {
    return b64Decode(str);
  }

  /* Compact MD5 for checksums only — not a password hash. */
  function md5(bytes) {
    const u8 = bytes instanceof Uint8Array ? bytes : enc.encode(String(bytes));
    function add32(a, b) { return (a + b) & 0xffffffff; }
    function cmn(q, a, b, x, s, t) {
      a = add32(add32(a, q), add32(x, t));
      return add32((a << s) | (a >>> (32 - s)), b);
    }
    function ff(a, b, c, d, x, s, t) { return cmn((b & c) | ((~b) & d), a, b, x, s, t); }
    function gg(a, b, c, d, x, s, t) { return cmn((b & d) | (c & (~d)), a, b, x, s, t); }
    function hh(a, b, c, d, x, s, t) { return cmn(b ^ c ^ d, a, b, x, s, t); }
    function ii(a, b, c, d, x, s, t) { return cmn(c ^ (b | (~d)), a, b, x, s, t); }
    const n = u8.length;
    const tail = n % 64;
    const pad = tail < 56 ? 64 - tail : 128 - tail;
    const buf = new Uint8Array(n + pad);
    buf.set(u8);
    buf[n] = 0x80;
    const view = new DataView(buf.buffer);
    view.setUint32(buf.length - 8, n * 8, true);
    let a = 1732584193, b = -271733879, c = -1732584194, d = 271733878;
    const x = new Int32Array(16);
    for (let i = 0; i < buf.length; i += 64) {
      for (let j = 0; j < 16; j++) x[j] = view.getInt32(i + j * 4, true);
      const aa = a, bb = b, cc = c, dd = d;
      a = ff(a, b, c, d, x[0], 7, -680876936); d = ff(d, a, b, c, x[1], 12, -389564586);
      c = ff(c, d, a, b, x[2], 17, 606105819); b = ff(b, c, d, a, x[3], 22, -1044525330);
      a = ff(a, b, c, d, x[4], 7, -176418897); d = ff(d, a, b, c, x[5], 12, 1200080426);
      c = ff(c, d, a, b, x[6], 17, -1473231341); b = ff(b, c, d, a, x[7], 22, -45705983);
      a = ff(a, b, c, d, x[8], 7, 1770035416); d = ff(d, a, b, c, x[9], 12, -1958414417);
      c = ff(c, d, a, b, x[10], 17, -42063); b = ff(b, c, d, a, x[11], 22, -1990404162);
      a = ff(a, b, c, d, x[12], 7, 1804603682); d = ff(d, a, b, c, x[13], 12, -40341101);
      c = ff(c, d, a, b, x[14], 17, -1502002290); b = ff(b, c, d, a, x[15], 22, 1236535329);
      a = gg(a, b, c, d, x[1], 5, -165796510); d = gg(d, a, b, c, x[6], 9, -1069501632);
      c = gg(c, d, a, b, x[11], 14, 643717713); b = gg(b, c, d, a, x[0], 20, -373897302);
      a = gg(a, b, c, d, x[5], 5, -701558691); d = gg(d, a, b, c, x[10], 9, 38016083);
      c = gg(c, d, a, b, x[15], 14, -660478335); b = gg(b, c, d, a, x[4], 20, -405537848);
      a = gg(a, b, c, d, x[9], 5, 568446438); d = gg(d, a, b, c, x[14], 9, -1019803690);
      c = gg(c, d, a, b, x[3], 14, -187363961); b = gg(b, c, d, a, x[8], 20, 1163531501);
      a = gg(a, b, c, d, x[13], 5, -1444681467); d = gg(d, a, b, c, x[2], 9, -51403784);
      c = gg(c, d, a, b, x[7], 14, 1735328473); b = gg(b, c, d, a, x[12], 20, -1926607734);
      a = hh(a, b, c, d, x[5], 4, -378558); d = hh(d, a, b, c, x[8], 11, -2022574463);
      c = hh(c, d, a, b, x[11], 16, 1839030562); b = hh(b, c, d, a, x[14], 23, -35309556);
      a = hh(a, b, c, d, x[1], 4, -1530992060); d = hh(d, a, b, c, x[4], 11, 1272893353);
      c = hh(c, d, a, b, x[7], 16, -155497632); b = hh(b, c, d, a, x[10], 23, -1094730640);
      a = hh(a, b, c, d, x[13], 4, 681279174); d = hh(d, a, b, c, x[0], 11, -358537222);
      c = hh(c, d, a, b, x[3], 16, -722521979); b = hh(b, c, d, a, x[6], 23, 76029189);
      a = hh(a, b, c, d, x[9], 4, -640364487); d = hh(d, a, b, c, x[12], 11, -421815835);
      c = hh(c, d, a, b, x[15], 16, 530742520); b = hh(b, c, d, a, x[2], 23, -995338651);
      a = ii(a, b, c, d, x[0], 6, -198630844); d = ii(d, a, b, c, x[7], 10, 1126891415);
      c = ii(c, d, a, b, x[14], 15, -1416354905); b = ii(b, c, d, a, x[5], 21, -57434055);
      a = ii(a, b, c, d, x[12], 6, 1700485571); d = ii(d, a, b, c, x[3], 10, -1894986606);
      c = ii(c, d, a, b, x[10], 15, -1051523); b = ii(b, c, d, a, x[1], 21, -2054922799);
      a = ii(a, b, c, d, x[8], 6, 1873313359); d = ii(d, a, b, c, x[15], 10, -30611744);
      c = ii(c, d, a, b, x[6], 15, -1560198380); b = ii(b, c, d, a, x[13], 21, 1309151649);
      a = ii(a, b, c, d, x[4], 6, -145523070); d = ii(d, a, b, c, x[11], 10, -1120210379);
      c = ii(c, d, a, b, x[2], 15, 718787259); b = ii(b, c, d, a, x[9], 21, -343485551);
      a = add32(a, aa); b = add32(b, bb); c = add32(c, cc); d = add32(d, dd);
    }
    function hex32(n) {
      const u = n < 0 ? n + 0x100000000 : n;
      const s = ("00000000" + u.toString(16)).slice(-8);
      return s.slice(6, 8) + s.slice(4, 6) + s.slice(2, 4) + s.slice(0, 2);
    }
    return hex32(a) + hex32(b) + hex32(c) + hex32(d);
  }

  async function digest(algo, bytes) {
    const buf = await crypto.subtle.digest(algo, bytes);
    return toHex(buf);
  }

  function prettyJson(text, minify, sortKeys) {
    const parsed = JSON.parse(text);
    const value = sortKeys ? sortDeep(parsed) : parsed;
    return minify ? JSON.stringify(value) : JSON.stringify(value, null, 2);
  }
  function sortDeep(v) {
    if (Array.isArray(v)) return v.map(sortDeep);
    if (v && typeof v === "object") {
      const out = {};
      Object.keys(v).sort().forEach(function (k) { out[k] = sortDeep(v[k]); });
      return out;
    }
    return v;
  }

  function uuidv4() {
    if (crypto.randomUUID) return crypto.randomUUID();
    const b = new Uint8Array(16);
    crypto.getRandomValues(b);
    b[6] = (b[6] & 0x0f) | 0x40;
    b[8] = (b[8] & 0x3f) | 0x80;
    const h = toHex(b);
    return h.slice(0, 8) + "-" + h.slice(8, 12) + "-" + h.slice(12, 16) + "-" + h.slice(16, 20) + "-" + h.slice(20);
  }

  function decodeJwt(token) {
    const parts = String(token).trim().split(".");
    if (parts.length < 2) throw new Error("A JWT has two or three dot-separated parts.");
    function piece(p) {
      const json = fromBytes(b64urlDecode(p));
      return JSON.parse(json);
    }
    const header = piece(parts[0]);
    const payload = piece(parts[1]);
    const sig = parts[2] || "";
    const notes = [];
    if (parts.length < 3) notes.push("No signature segment (unsecured JWT).");
    if (payload.exp) {
      const exp = Number(payload.exp) * 1000;
      notes.push("exp " + new Date(exp).toISOString() + (Date.now() > exp ? " (expired)" : " (not expired)"));
    }
    if (payload.nbf) {
      const nbf = Number(payload.nbf) * 1000;
      notes.push("nbf " + new Date(nbf).toISOString() + (Date.now() < nbf ? " (not yet valid)" : ""));
    }
    if (payload.iat) notes.push("iat " + new Date(Number(payload.iat) * 1000).toISOString());
    notes.push("Signature is displayed only. This page does not verify it.");
    return { header, payload, signature: sig, notes };
  }

  function lcsDiff(aLines, bLines) {
    const n = aLines.length, m = bLines.length;
    const limit = 4000;
    if (n > limit || m > limit) {
      return [{ type: "meta", text: "Each side is capped at " + limit + " lines. Trim the input." }];
    }
    const dp = new Array(n + 1);
    for (let i = 0; i <= n; i++) {
      dp[i] = new Uint16Array(m + 1);
    }
    for (let i = n - 1; i >= 0; i--) {
      for (let j = m - 1; j >= 0; j--) {
        dp[i][j] = aLines[i] === bLines[j] ? dp[i + 1][j + 1] + 1 : Math.max(dp[i + 1][j], dp[i][j + 1]);
      }
    }
    const out = [];
    let i = 0, j = 0;
    while (i < n && j < m) {
      if (aLines[i] === bLines[j]) {
        out.push({ type: "same", text: aLines[i] });
        i++; j++;
      } else if (dp[i + 1][j] >= dp[i][j + 1]) {
        out.push({ type: "del", text: aLines[i] });
        i++;
      } else {
        out.push({ type: "add", text: bLines[j] });
        j++;
      }
    }
    while (i < n) out.push({ type: "del", text: aLines[i++] });
    while (j < m) out.push({ type: "add", text: bLines[j++] });
    return out;
  }

  function renderDiff(node, rows) {
    node.textContent = "";
    rows.forEach(function (r) {
      const line = el("div", { class: "diff-line diff-" + r.type });
      const mark = r.type === "add" ? "+" : r.type === "del" ? "-" : r.type === "meta" ? "!" : " ";
      line.appendChild(el("span", { class: "diff-mark", text: mark }));
      line.appendChild(el("span", { class: "diff-text", text: r.text }));
      node.appendChild(line);
    });
  }

  const HTTP_CODES = {
    100: "Continue", 101: "Switching Protocols", 102: "Processing", 103: "Early Hints",
    200: "OK", 201: "Created", 202: "Accepted", 203: "Non-Authoritative Information",
    204: "No Content", 205: "Reset Content", 206: "Partial Content", 207: "Multi-Status",
    208: "Already Reported", 226: "IM Used",
    300: "Multiple Choices", 301: "Moved Permanently", 302: "Found", 303: "See Other",
    304: "Not Modified", 307: "Temporary Redirect", 308: "Permanent Redirect",
    400: "Bad Request", 401: "Unauthorized", 402: "Payment Required", 403: "Forbidden",
    404: "Not Found", 405: "Method Not Allowed", 406: "Not Acceptable",
    407: "Proxy Authentication Required", 408: "Request Timeout", 409: "Conflict",
    410: "Gone", 411: "Length Required", 412: "Precondition Failed", 413: "Payload Too Large",
    414: "URI Too Long", 415: "Unsupported Media Type", 416: "Range Not Satisfiable",
    417: "Expectation Failed", 418: "I'm a teapot", 421: "Misdirected Request",
    422: "Unprocessable Entity", 423: "Locked", 424: "Failed Dependency",
    425: "Too Early", 426: "Upgrade Required", 428: "Precondition Required",
    429: "Too Many Requests", 431: "Request Header Fields Too Large",
    451: "Unavailable For Legal Reasons",
    500: "Internal Server Error", 501: "Not Implemented", 502: "Bad Gateway",
    503: "Service Unavailable", 504: "Gateway Timeout", 505: "HTTP Version Not Supported",
    506: "Variant Also Negotiates", 507: "Insufficient Storage", 508: "Loop Detected",
    510: "Not Extended", 511: "Network Authentication Required"
  };

  function parseIPv4(s) {
    const p = String(s).trim().split(".");
    if (p.length !== 4) throw new Error("Need four dotted octets.");
    const n = p.map(function (x) {
      if (!/^\d+$/.test(x)) throw new Error("Bad octet.");
      const v = Number(x);
      if (v < 0 || v > 255) throw new Error("Octet out of range.");
      return v;
    });
    return ((n[0] << 24) >>> 0) + (n[1] << 16) + (n[2] << 8) + n[3];
  }
  function fmtIPv4(n) {
    n = n >>> 0;
    return [(n >>> 24) & 255, (n >>> 16) & 255, (n >>> 8) & 255, n & 255].join(".");
  }
  function cidrInfo(input) {
    const t = String(input).trim();
    let ip, prefix;
    const slash = t.match(/^(.+?)\/(\d{1,2})$/);
    const spaced = t.match(/^(\d+\.\d+\.\d+\.\d+)\s+(\d+\.\d+\.\d+\.\d+)$/);
    if (slash) {
      ip = parseIPv4(slash[1]);
      prefix = Number(slash[2]);
    } else if (spaced) {
      ip = parseIPv4(spaced[1]);
      const mask = parseIPv4(spaced[2]);
      prefix = 0;
      for (let i = 31; i >= 0; i--) {
        if (mask & (1 << i)) prefix++;
        else break;
      }
      if (mask !== (prefix === 0 ? 0 : (0xffffffff << (32 - prefix)) >>> 0)) {
        throw new Error("Mask is not contiguous.");
      }
    } else {
      throw new Error("Use 10.0.0.0/24 or 10.0.0.1 255.255.255.0");
    }
    if (prefix < 0 || prefix > 32) throw new Error("Prefix must be 0–32.");
    const mask = prefix === 0 ? 0 : (0xffffffff << (32 - prefix)) >>> 0;
    const network = (ip & mask) >>> 0;
    const wildcard = (~mask) >>> 0;
    const broadcast = (network | wildcard) >>> 0;
    const size = wildcard + 1;
    const first = prefix >= 31 ? network : (network + 1) >>> 0;
    const last = prefix >= 31 ? broadcast : (broadcast - 1) >>> 0;
    return {
      input: t,
      address: fmtIPv4(ip),
      prefix: prefix,
      netmask: fmtIPv4(mask),
      wildcard: fmtIPv4(wildcard),
      network: fmtIPv4(network) + "/" + prefix,
      networkAddr: fmtIPv4(network),
      broadcast: fmtIPv4(broadcast),
      firstHost: fmtIPv4(first),
      lastHost: fmtIPv4(last),
      hosts: prefix >= 31 ? size : size - 2,
      total: size,
      ptr: fmtIPv4(ip).split(".").reverse().join(".") + ".in-addr.arpa"
    };
  }

  function parseCronField(field, min, max, names) {
    if (names && /^[A-Za-z]+$/.test(field)) {
      const idx = names.indexOf(field.slice(0, 3).toLowerCase());
      if (idx >= 0) field = String(idx);
    }
    const values = {};
    field.split(",").forEach(function (part) {
      let step = 1;
      let range = part;
      if (part.indexOf("/") >= 0) {
        const bits = part.split("/");
        range = bits[0];
        step = Number(bits[1]);
        if (!step) throw new Error("Bad step in " + field);
      }
      let start, end;
      if (range === "*") { start = min; end = max; }
      else if (range.indexOf("-") >= 0) {
        const ab = range.split("-");
        start = Number(ab[0]); end = Number(ab[1]);
      } else {
        start = end = Number(range);
      }
      if (isNaN(start) || isNaN(end) || start < min || end > max || start > end) {
        throw new Error("Bad field: " + field);
      }
      for (let v = start; v <= end; v += step) values[v] = true;
    });
    return values;
  }
  function cronNext(expr, count) {
    const raw = String(expr).trim();
    const specials = {
      "@yearly": "0 0 1 1 *",
      "@annually": "0 0 1 1 *",
      "@monthly": "0 0 1 * *",
      "@weekly": "0 0 * * 0",
      "@daily": "0 0 * * *",
      "@midnight": "0 0 * * *",
      "@hourly": "0 * * * *"
    };
    const five = (specials[raw] || raw).split(/\s+/);
    if (five.length !== 5) throw new Error("Need five fields: minute hour day month weekday.");
    const months = ["jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec"];
    const dows = ["sun","mon","tue","wed","thu","fri","sat"];
    const minute = parseCronField(five[0], 0, 59);
    const hour = parseCronField(five[1], 0, 23);
    const day = parseCronField(five[2], 1, 31);
    const month = parseCronField(five[3], 1, 12, months);
    const weekday = parseCronField(five[4], 0, 7, dows);
    if (weekday[7]) weekday[0] = true;
    const hits = [];
    const d = new Date();
    d.setSeconds(0, 0);
    d.setMinutes(d.getMinutes() + 1);
    const limit = 366 * 24 * 60;
    for (let i = 0; i < limit && hits.length < count; i++) {
      const mon = d.getMonth() + 1;
      const date = d.getDate();
      const dow = d.getDay();
      const hr = d.getHours();
      const min = d.getMinutes();
      const dayOk = five[2] === "*" || day[date];
      const dowOk = five[4] === "*" || weekday[dow];
      const both = five[2] !== "*" && five[4] !== "*";
      const calendar = both ? (dayOk || dowOk) : (dayOk && dowOk);
      if (month[mon] && calendar && hour[hr] && minute[min]) hits.push(new Date(d.getTime()));
      d.setMinutes(d.getMinutes() + 1);
    }
    function describe() {
      return five[0] + " " + five[1] + " " + five[2] + " " + five[3] + " " + five[4];
    }
    return { normalized: describe(), hits: hits };
  }

  const OID = {
    "2.5.4.3": "CN", "2.5.4.6": "C", "2.5.4.7": "L", "2.5.4.8": "ST",
    "2.5.4.10": "O", "2.5.4.11": "OU", "2.5.4.5": "serialNumber",
    "1.2.840.113549.1.1.1": "rsaEncryption",
    "1.2.840.113549.1.1.11": "sha256WithRSAEncryption",
    "1.2.840.113549.1.1.12": "sha384WithRSAEncryption",
    "1.2.840.113549.1.1.13": "sha512WithRSAEncryption",
    "1.2.840.10045.2.1": "ecPublicKey",
    "1.2.840.10045.4.3.2": "ecdsa-with-SHA256",
    "2.5.29.17": "subjectAltName",
    "2.5.29.15": "keyUsage",
    "2.5.29.19": "basicConstraints"
  };
  function derTLV(bytes, offset) {
    if (offset >= bytes.length) throw new Error("Truncated DER.");
    const tag = bytes[offset];
    let i = offset + 1;
    let len = bytes[i++];
    if (len & 0x80) {
      const n = len & 0x7f;
      len = 0;
      for (let k = 0; k < n; k++) len = (len << 8) | bytes[i++];
    }
    return { tag: tag, start: offset, headerEnd: i, end: i + len, len: len };
  }
  function derChildren(bytes, tlv) {
    const kids = [];
    let p = tlv.headerEnd;
    while (p < tlv.end) {
      const c = derTLV(bytes, p);
      kids.push(c);
      p = c.end;
    }
    return kids;
  }
  function derOid(bytes, tlv) {
    const v = bytes.subarray(tlv.headerEnd, tlv.end);
    if (!v.length) return "";
    const parts = [Math.floor(v[0] / 40), v[0] % 40];
    let acc = 0;
    for (let i = 1; i < v.length; i++) {
      acc = (acc << 7) | (v[i] & 0x7f);
      if (!(v[i] & 0x80)) { parts.push(acc); acc = 0; }
    }
    return parts.join(".");
  }
  function derTime(bytes, tlv) {
    const s = fromBytes(bytes.subarray(tlv.headerEnd, tlv.end));
    if (tlv.tag === 0x17 && s.length >= 12) {
      const yy = Number(s.slice(0, 2));
      const year = yy >= 50 ? 1900 + yy : 2000 + yy;
      return year + "-" + s.slice(2, 4) + "-" + s.slice(4, 6) + "T" + s.slice(6, 8) + ":" + s.slice(8, 10) + ":" + s.slice(10, 12) + "Z";
    }
    if (tlv.tag === 0x18 && s.length >= 14) {
      return s.slice(0, 4) + "-" + s.slice(4, 6) + "-" + s.slice(6, 8) + "T" + s.slice(8, 10) + ":" + s.slice(10, 12) + ":" + s.slice(12, 14) + "Z";
    }
    return s;
  }
  function derPrintable(bytes, tlv) {
    return fromBytes(bytes.subarray(tlv.headerEnd, tlv.end));
  }
  function derName(bytes, tlv) {
    const rdns = [];
    derChildren(bytes, tlv).forEach(function (set) {
      derChildren(bytes, set).forEach(function (seq) {
        const kids = derChildren(bytes, seq);
        if (kids.length < 2) return;
        const oid = derOid(bytes, kids[0]);
        const val = derPrintable(bytes, kids[1]);
        rdns.push((OID[oid] || oid) + "=" + val);
      });
    });
    return rdns.join(", ");
  }
  function parsePem(text) {
    const blocks = [];
    const re = /-----BEGIN ([^-]+)-----([\s\S]*?)-----END \1-----/g;
    let m;
    while ((m = re.exec(text))) {
      const type = m[1].trim();
      const der = b64Decode(m[2].replace(/\s+/g, ""));
      const info = { type: type, bytes: der.length, sha256: null, details: [] };
      blocks.push({ info: info, der: der });
    }
    if (!blocks.length) throw new Error("No PEM block found. Paste BEGIN / END lines.");
    return blocks;
  }
  async function describePem(text) {
    const blocks = parsePem(text);
    for (let b = 0; b < blocks.length; b++) {
      const der = blocks[b].der;
      const info = blocks[b].info;
      info.sha256 = await digest("SHA-256", der);
      info.fingerprint = info.sha256.match(/.{2}/g).join(":").toUpperCase();
      if (/CERTIFICATE/.test(info.type) && !/REQUEST/.test(info.type)) {
        try {
          const cert = derTLV(der, 0);
          const top = derChildren(der, cert);
          const tbs = top[0];
          const kids = derChildren(der, tbs);
          let idx = 0;
          if (kids[0] && kids[0].tag === 0xa0) idx = 1;
          info.details.push("serial " + toHex(der.subarray(kids[idx].headerEnd, kids[idx].end)));
          idx++;
          if (kids[idx]) {
            const algKids = derChildren(der, kids[idx]);
            if (algKids[0]) {
              const oid = derOid(der, algKids[0]);
              info.details.push("signature " + (OID[oid] || oid));
            }
          }
          idx++;
          if (kids[idx]) info.details.push("issuer " + derName(der, kids[idx]));
          idx++;
          if (kids[idx]) {
            const validity = derChildren(der, kids[idx]);
            if (validity[0]) info.details.push("not before " + derTime(der, validity[0]));
            if (validity[1]) info.details.push("not after " + derTime(der, validity[1]));
          }
          idx++;
          if (kids[idx]) info.details.push("subject " + derName(der, kids[idx]));
        } catch (e) {
          info.details.push("Could not fully parse X.509 (" + e.message + "). Fingerprint is still valid.");
        }
      } else {
        info.details.push("Decoded " + der.length + " DER bytes. Use openssl for a full dump if you need extensions.");
      }
    }
    return blocks.map(function (b) { return b.info; });
  }

  function slugify(s) {
    return String(s).toLowerCase().normalize("NFKD").replace(/[\u0300-\u036f]/g, "")
      .replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");
  }
  function snake(s) {
    return String(s).replace(/([a-z])([A-Z])/g, "$1_$2").replace(/[^A-Za-z0-9]+/g, "_")
      .replace(/^_+|_+$/g, "").toLowerCase();
  }
  function camel(s) {
    const p = snake(s).split("_");
    return p[0] + p.slice(1).map(function (w) { return w.charAt(0).toUpperCase() + w.slice(1); }).join("");
  }
  function pascal(s) {
    const c = camel(s);
    return c.charAt(0).toUpperCase() + c.slice(1);
  }
  function titleCase(s) {
    return String(s).toLowerCase().replace(/\b[\p{L}\p{N}]+/gu, function (w) {
      return w.charAt(0).toUpperCase() + w.slice(1);
    });
  }
  function kebab(s) { return snake(s).replace(/_/g, "-"); }
  function constant(s) { return snake(s).toUpperCase(); }

  function htmlEncode(s) {
    return String(s).replace(/[&<>"']/g, function (ch) {
      return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[ch];
    });
  }
  function htmlDecode(s) {
    const map = { amp: "&", lt: "<", gt: ">", quot: '"', apos: "'", nbsp: "\u00a0" };
    return String(s).replace(/&(#x?[0-9a-fA-F]+|[a-zA-Z]+);/g, function (_, ent) {
      if (ent[0] === "#") {
        const n = ent[1] === "x" || ent[1] === "X" ? parseInt(ent.slice(2), 16) : parseInt(ent.slice(1), 10);
        return String.fromCodePoint(n);
      }
      return map[ent] != null ? map[ent] : _;
    });
  }

  function parseUrlish(raw) {
    const t = String(raw).trim();
    if (!t) throw new Error("Paste a URL or a query string.");
    let url;
    try {
      url = new URL(t);
    } catch (e) {
      try { url = new URL("https://example.invalid/" + (t.charAt(0) === "?" ? t : "?" + t)); }
      catch (e2) { throw new Error("Could not parse that as a URL or query."); }
    }
    const params = [];
    url.searchParams.forEach(function (v, k) { params.push({ key: k, value: v }); });
    return {
      href: url.href,
      protocol: url.protocol,
      username: url.username,
      host: url.host,
      hostname: url.hostname,
      port: url.port,
      pathname: url.pathname,
      search: url.search,
      hash: url.hash,
      origin: url.origin,
      params: params
    };
  }

  function hexDump(bytes) {
    const u8 = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
    const lines = [];
    for (let i = 0; i < u8.length; i += 16) {
      const slice = u8.subarray(i, i + 16);
      const hex = [];
      let ascii = "";
      for (let j = 0; j < 16; j++) {
        if (j < slice.length) {
          hex.push((slice[j] + 256).toString(16).slice(1));
          ascii += slice[j] >= 32 && slice[j] < 127 ? String.fromCharCode(slice[j]) : ".";
        } else hex.push("  ");
        if (j === 7) hex.push("");
      }
      lines.push(("00000000" + i.toString(16)).slice(-8) + "  " + hex.join(" ") + "  |" + ascii + "|");
    }
    return lines.join("\n");
  }

  function parseBase(text, base) {
    const t = String(text).trim().replace(/[\s_]/g, "");
    if (!t) throw new Error("Enter a number.");
    let b = base;
    let s = t;
    if (b === "auto") {
      if (/^0x/i.test(s) || /^[0-9a-f]+h$/i.test(s)) b = 16;
      else if (/^0b/i.test(s)) b = 2;
      else if (/^0o/i.test(s)) b = 8;
      else b = 10;
    }
    s = s.replace(/^0x/i, "").replace(/h$/i, "").replace(/^0b/i, "").replace(/^0o/i, "");
    if (s.charAt(0) === "-") throw new Error("Use unsigned values.");
    const n = BigInt("0b0") + (function () {
      const digits = "0123456789abcdefghijklmnopqrstuvwxyz";
      let acc = 0n;
      const bb = BigInt(b);
      for (let i = 0; i < s.length; i++) {
        const d = digits.indexOf(s.charAt(i).toLowerCase());
        if (d < 0 || d >= b) throw new Error("Digit not valid for base " + b);
        acc = acc * bb + BigInt(d);
      }
      return acc;
    })();
    return n;
  }
  function fmtBase(n, base) {
    return n.toString(base);
  }

  function randomString(len, alphabet) {
    if (len < 1 || len > 1024) throw new Error("Length must be 1–1024.");
    if (!alphabet) throw new Error("Pick at least one character set.");
    const out = [];
    const buf = new Uint32Array(len);
    crypto.getRandomValues(buf);
    for (let i = 0; i < len; i++) out.push(alphabet[buf[i] % alphabet.length]);
    return out.join("");
  }
  function entropyBits(len, alphabetLen) {
    if (!len || !alphabetLen) return 0;
    return len * Math.log2(alphabetLen);
  }

  function shellQuote(s) {
    if (/^[A-Za-z0-9_./:@%+=-]+$/.test(s)) return s;
    return "'" + String(s).replace(/'/g, "'\\''") + "'";
  }
  function buildCurl(opts) {
    const parts = ["curl"];
    if (opts.method && opts.method !== "GET") parts.push("-X", opts.method);
    if (opts.headers) {
      String(opts.headers).split(/\n/).forEach(function (line) {
        const t = line.trim();
        if (t) parts.push("-H", shellQuote(t));
      });
    }
    if (opts.body && opts.method !== "GET" && opts.method !== "HEAD") {
      parts.push("--data-binary", shellQuote(opts.body));
    }
    if (opts.insecure) parts.push("-k");
    parts.push("-i", shellQuote(opts.url));
    return parts.join(" ");
  }

  /* ---------- tool UIs ---------- */

  function mountJson(root) {
    const inn = textarea("json-in", '{ "ok": true }');
    const out = pre();
    const st = statusLine();
    const sort = el("input", { type: "checkbox", id: "json-sort" });
    function run(minify) {
      try {
        const text = prettyJson(inn.value, minify, sort.checked);
        setText(out, text);
        setStatus(st, "Valid JSON · " + text.length + " chars", "ok");
      } catch (e) {
        setStatus(st, e.message, "err");
      }
    }
    root.append(
      labelWrap("JSON", inn),
      row([
        el("label", { class: "check-inline" }, [sort, document.createTextNode(" Sort keys")]),
        btn("Format", "", function () { run(false); }),
        btn("Minify", "secondary", function () { run(true); }),
        btn("Copy", "secondary", function () { copyOut(out.textContent, this); })
      ]),
      st, out
    );
  }

  function mountBase64(root) {
    const inn = textarea("b64-in", "Paste text or Base64");
    const out = pre();
    const st = statusLine();
    const urlSafe = el("input", { type: "checkbox", id: "b64-url" });
    function encode() {
      try {
        setText(out, b64Encode(bytesOf(inn.value), urlSafe.checked));
        setStatus(st, "Encoded · " + out.textContent.length + " chars", "ok");
      } catch (e) { setStatus(st, e.message, "err"); }
    }
    function decode() {
      try {
        setText(out, fromBytes(b64Decode(inn.value)));
        setStatus(st, "Decoded", "ok");
      } catch (e) { setStatus(st, e.message, "err"); }
    }
    root.append(
      labelWrap("Input", inn),
      row([
        el("label", { class: "check-inline" }, [urlSafe, document.createTextNode(" URL-safe")]),
        btn("Encode", "", encode),
        btn("Decode", "secondary", decode),
        btn("Copy", "secondary", function () { copyOut(out.textContent, this); })
      ]),
      st, out
    );
  }

  function mountUrl(root) {
    const inn = textarea("urlenc-in", "hello world / path?q=1");
    const out = pre();
    const st = statusLine();
    root.append(
      labelWrap("Text", inn),
      row([
        btn("Encode component", "", function () {
          setText(out, encodeURIComponent(inn.value));
          setStatus(st, "encodeURIComponent", "ok");
        }),
        btn("Encode URI", "secondary", function () {
          setText(out, encodeURI(inn.value));
          setStatus(st, "encodeURI (keeps :/?#)", "ok");
        }),
        btn("Decode", "secondary", function () {
          try {
            setText(out, decodeURIComponent(inn.value.replace(/\+/g, "%20")));
            setStatus(st, "Decoded (+ as space)", "ok");
          } catch (e) { setStatus(st, e.message, "err"); }
        }),
        btn("Copy", "secondary", function () { copyOut(out.textContent, this); })
      ]),
      st, out
    );
  }

  function mountHash(root) {
    const inn = textarea("hash-in", "payload");
    const key = input("hash-key", "text", "HMAC key (optional)");
    const out = pre();
    const st = statusLine();
    async function run() {
      try {
        const bytes = bytesOf(inn.value);
        const k = key.value;
        const lines = [];
        lines.push("md5     " + md5(bytes) + "   (checksum only)");
        lines.push("sha1    " + await digest("SHA-1", bytes));
        lines.push("sha256  " + await digest("SHA-256", bytes));
        lines.push("sha384  " + await digest("SHA-384", bytes));
        lines.push("sha512  " + await digest("SHA-512", bytes));
        if (k) {
          async function hmac(algo) {
            const cryptoKey = await crypto.subtle.importKey(
              "raw", bytesOf(k), { name: "HMAC", hash: algo }, false, ["sign"]
            );
            const sig = await crypto.subtle.sign("HMAC", cryptoKey, bytes);
            return toHex(sig);
          }
          lines.push("");
          lines.push("hmac-sha1    " + await hmac("SHA-1"));
          lines.push("hmac-sha256  " + await hmac("SHA-256"));
          lines.push("hmac-sha512  " + await hmac("SHA-512"));
        }
        setText(out, lines.join("\n"));
        setStatus(st, "Hashed locally with Web Crypto (MD5 is a local checksum).", "ok");
      } catch (e) { setStatus(st, e.message, "err"); }
    }
    root.append(
      labelWrap("Data", inn),
      labelWrap("HMAC key", key, "Leave blank for plain digests."),
      row([
        btn("Hash", "", run),
        btn("Copy", "secondary", function () { copyOut(out.textContent, this); })
      ]),
      st, out
    );
  }

  function mountUuid(root) {
    const count = input("uuid-n", "number", "", "5");
    count.min = "1"; count.max = "100";
    const out = pre();
    const st = statusLine();
    function gen() {
      const n = Math.min(100, Math.max(1, Number(count.value) || 1));
      const list = [];
      for (let i = 0; i < n; i++) list.push(uuidv4());
      setText(out, list.join("\n"));
      setStatus(st, n + " UUID v4", "ok");
    }
    gen();
    root.append(
      labelWrap("How many", count),
      row([
        btn("Generate", "", gen),
        btn("Copy", "secondary", function () { copyOut(out.textContent, this); })
      ]),
      st, out
    );
  }

  function mountTime(root) {
    const inn = input("time-in", "text", "1714000000 or 2024-04-25T00:00:00Z");
    const out = pre();
    const st = statusLine();
    const live = el("p", { class: "tool-live" });
    let timer = null;
    function tick() {
      const now = new Date();
      live.textContent = "now  " + Math.floor(now.getTime() / 1000) + " s   ·   " + now.getTime() + " ms   ·   " + now.toISOString();
    }
    function convert() {
      const t = inn.value.trim();
      if (!t) {
        const n = new Date();
        inn.value = String(Math.floor(n.getTime() / 1000));
      }
      const raw = inn.value.trim();
      let d;
      if (/^-?\d{10}$/.test(raw)) d = new Date(Number(raw) * 1000);
      else if (/^-?\d{13}$/.test(raw)) d = new Date(Number(raw));
      else {
        const parsed = Date.parse(raw);
        if (isNaN(parsed)) {
          setStatus(st, "Could not parse. Use epoch seconds, milliseconds, or ISO-8601.", "err");
          return;
        }
        d = new Date(parsed);
      }
      const sec = Math.floor(d.getTime() / 1000);
      const lines = [
        "unix s     " + sec,
        "unix ms    " + d.getTime(),
        "ISO UTC    " + d.toISOString(),
        "local      " + d.toString(),
        "UTC date   " + d.toUTCString(),
        "relative   " + relative(d)
      ];
      setText(out, lines.join("\n"));
      setStatus(st, "Converted", "ok");
    }
    function relative(d) {
      const s = Math.round((d.getTime() - Date.now()) / 1000);
      const abs = Math.abs(s);
      const unit = abs < 60 ? abs + "s" : abs < 3600 ? Math.round(abs / 60) + "m" : abs < 86400 ? Math.round(abs / 3600) + "h" : Math.round(abs / 86400) + "d";
      return s === 0 ? "now" : s < 0 ? unit + " ago" : "in " + unit;
    }
    tick();
    timer = setInterval(tick, 1000);
    root.append(
      live,
      labelWrap("Epoch or date", inn),
      row([
        btn("Convert", "", convert),
        btn("Use now", "secondary", function () {
          inn.value = String(Math.floor(Date.now() / 1000));
          convert();
        }),
        btn("Copy", "secondary", function () { copyOut(out.textContent, this); })
      ]),
      st, out
    );
    root._toolsCleanup = function () { clearInterval(timer); };
  }

  function mountJwt(root) {
    const inn = textarea("jwt-in", "eyJhbGciOi...");
    const out = pre();
    const st = statusLine();
    function run() {
      try {
        const d = decodeJwt(inn.value);
        const text = [
          "// header",
          JSON.stringify(d.header, null, 2),
          "",
          "// payload",
          JSON.stringify(d.payload, null, 2),
          "",
          "// signature (unverified)",
          d.signature || "(none)",
          "",
          d.notes.map(function (n) { return "// " + n; }).join("\n")
        ].join("\n");
        setText(out, text);
        setStatus(st, "Decoded. Not verified.", "ok");
      } catch (e) { setStatus(st, e.message, "err"); }
    }
    root.append(
      labelWrap("JWT", inn),
      row([
        btn("Decode", "", run),
        btn("Copy", "secondary", function () { copyOut(out.textContent, this); })
      ]),
      st, out
    );
  }

  function mountRegex(root) {
    const pattern = input("re-pat", "text", "^[a-z0-9_-]+$");
    const subject = textarea("re-sub", "one line per test, or a blob of text");
    const repl = input("re-rep", "text", "replacement with $1");
    const out = pre();
    const st = statusLine();
    const flags = { g: true, i: false, m: false, s: false, u: true };
    const flagRow = el("div", { class: "tool-row" });
    Object.keys(flags).forEach(function (f) {
      const box = el("input", { type: "checkbox", id: "re-" + f });
      box.checked = flags[f];
      box.addEventListener("change", function () { flags[f] = box.checked; });
      flagRow.appendChild(el("label", { class: "check-inline" }, [box, document.createTextNode(" " + f)]));
    });
    function buildRe() {
      const f = Object.keys(flags).filter(function (k) { return flags[k]; }).join("");
      return new RegExp(pattern.value, f);
    }
    function test() {
      try {
        const re = buildRe();
        const text = subject.value;
        const matches = [];
        if (re.global) {
          let m, n = 0;
          re.lastIndex = 0;
          while ((m = re.exec(text)) && n++ < 200) {
            matches.push("#" + n + " @" + m.index + "  " + JSON.stringify(m[0]) +
              (m.length > 1 ? "  groups " + JSON.stringify(m.slice(1)) : ""));
            if (m[0] === "") re.lastIndex++;
          }
        } else {
          const m = re.exec(text);
          if (m) matches.push("match @" + m.index + "  " + JSON.stringify(m[0]));
        }
        setText(out, matches.length ? matches.join("\n") : "(no match)");
        setStatus(st, matches.length ? matches.length + " match(es)" : "No match", matches.length ? "ok" : "");
      } catch (e) { setStatus(st, e.message, "err"); }
    }
    function replace() {
      try {
        const re = buildRe();
        setText(out, subject.value.replace(re, repl.value));
        setStatus(st, "Replaced", "ok");
      } catch (e) { setStatus(st, e.message, "err"); }
    }
    root.append(
      labelWrap("Pattern", pattern),
      flagRow,
      labelWrap("Subject", subject),
      labelWrap("Replacement", repl),
      row([
        btn("Test", "", test),
        btn("Replace", "secondary", replace),
        btn("Copy", "secondary", function () { copyOut(out.textContent, this); })
      ]),
      st, out
    );
  }

  function mountDiff(root) {
    const a = textarea("diff-a", "left / original");
    const b = textarea("diff-b", "right / new");
    a.classList.add("tool-in-half");
    b.classList.add("tool-in-half");
    const out = el("div", { class: "tool-out diff-out", tabindex: "0" });
    const st = statusLine();
    function run() {
      const left = a.value.split("\n");
      const right = b.value.split("\n");
      const rows = lcsDiff(left, right);
      renderDiff(out, rows);
      const add = rows.filter(function (r) { return r.type === "add"; }).length;
      const del = rows.filter(function (r) { return r.type === "del"; }).length;
      setStatus(st, "+" + add + "  −" + del, "ok");
    }
    const pair = el("div", { class: "diff-pair" });
    pair.append(
      labelWrap("Original", a),
      labelWrap("New", b)
    );
    root.append(
      pair,
      row([
        btn("Diff", "", run),
        btn("Copy unified", "secondary", function () {
          const lines = $all(".diff-line", out).map(function (n) { return n.textContent; });
          copyOut(lines.join("\n"), this);
        })
      ]),
      st, out
    );
  }

  function mountQuery(root) {
    const inn = textarea("q-in", "https://example.com/path?q=1&q=2#frag");
    const out = pre();
    const st = statusLine();
    function run() {
      try {
        const u = parseUrlish(inn.value);
        const lines = [
          "href      " + u.href,
          "protocol  " + u.protocol,
          "host      " + u.host,
          "hostname  " + u.hostname,
          "port      " + (u.port || "(default)"),
          "pathname  " + u.pathname,
          "hash      " + u.hash,
          "user      " + (u.username || "(none)"),
          "",
          "query params"
        ];
        if (!u.params.length) lines.push("(none)");
        u.params.forEach(function (p) { lines.push(p.key + " = " + p.value); });
        setText(out, lines.join("\n"));
        setStatus(st, u.params.length + " param(s)", "ok");
      } catch (e) { setStatus(st, e.message, "err"); }
    }
    root.append(
      labelWrap("URL or query string", inn),
      row([
        btn("Parse", "", run),
        btn("Copy", "secondary", function () { copyOut(out.textContent, this); })
      ]),
      st, out
    );
  }

  function mountCron(root) {
    const inn = input("cron-in", "text", "*/5 * * * *", "*/5 * * * *");
    const out = pre();
    const st = statusLine();
    function run() {
      try {
        const r = cronNext(inn.value, 8);
        const lines = ["expression  " + r.normalized, "timezone    " + Intl.DateTimeFormat().resolvedOptions().timeZone, ""];
        if (!r.hits.length) lines.push("No fire times in the next year.");
        r.hits.forEach(function (d, i) {
          lines.push((i + 1) + ".  " + d.toString() + "   ·   " + d.toISOString());
        });
        setText(out, lines.join("\n"));
        setStatus(st, r.hits.length + " upcoming (local clock)", "ok");
      } catch (e) { setStatus(st, e.message, "err"); }
    }
    run();
    root.append(
      labelWrap("Cron (5 fields or @hourly / @daily / @weekly / @monthly / @yearly)", inn),
      row([
        btn("Explain", "", run),
        btn("Copy", "secondary", function () { copyOut(out.textContent, this); })
      ]),
      st, out
    );
  }

  function mountHttp(root) {
    const q = input("http-q", "search", "Filter — 404, gateway, teapot");
    const out = el("div", { class: "http-codes" });
    const st = statusLine();
    function render() {
      const needle = q.value.trim().toLowerCase();
      out.textContent = "";
      let n = 0;
      Object.keys(HTTP_CODES).forEach(function (code) {
        const name = HTTP_CODES[code];
        const blob = code + " " + name;
        if (needle && blob.toLowerCase().indexOf(needle) < 0) return;
        n++;
        const item = el("button", { type: "button", class: "http-code" });
        item.appendChild(el("strong", { text: code }));
        item.appendChild(el("span", { text: name }));
        item.addEventListener("click", function () { copyOut(code + " " + name, item); });
        out.appendChild(item);
      });
      setStatus(st, n + " codes · click to copy", "");
    }
    q.addEventListener("input", render);
    render();
    root.append(labelWrap("Search", q), st, out);
  }

  function mountCidr(root) {
    const inn = input("cidr-in", "text", "10.0.0.0/24", "10.0.0.0/24");
    const out = pre();
    const st = statusLine();
    function run() {
      try {
        const c = cidrInfo(inn.value);
        setText(out, [
          "network     " + c.network,
          "netmask     " + c.netmask,
          "wildcard    " + c.wildcard,
          "broadcast   " + c.broadcast,
          "first host  " + c.firstHost,
          "last host   " + c.lastHost,
          "addresses   " + c.total,
          "usable      " + c.hosts,
          "PTR zone    " + c.ptr
        ].join("\n"));
        setStatus(st, c.network, "ok");
      } catch (e) { setStatus(st, e.message, "err"); }
    }
    run();
    root.append(
      labelWrap("CIDR or IP + mask", inn),
      row([
        btn("Calculate", "", run),
        btn("Copy", "secondary", function () { copyOut(out.textContent, this); })
      ]),
      st, out
    );
  }

  function mountPassword(root) {
    const len = input("pw-len", "number", "", "20");
    len.min = "4"; len.max = "128";
    const sets = [
      { id: "lower", label: "a–z", chars: "abcdefghijkmnopqrstuvwxyz", on: true },
      { id: "upper", label: "A–Z", chars: "ABCDEFGHJKLMNPQRSTUVWXYZ", on: true },
      { id: "digits", label: "2–9", chars: "23456789", on: true },
      { id: "symbols", label: "symbols", chars: "!@#$%^&*()-_=+[]{};:,.?", on: true },
      { id: "similar", label: "include 0O1lI", chars: "0O1lI", on: false }
    ];
    const boxes = el("div", { class: "tool-row" });
    const flags = {};
    sets.forEach(function (s) {
      const box = el("input", { type: "checkbox", id: "pw-" + s.id });
      box.checked = s.on;
      flags[s.id] = box;
      boxes.appendChild(el("label", { class: "check-inline" }, [box, document.createTextNode(" " + s.label)]));
    });
    const out = pre();
    const st = statusLine();
    function alphabet() {
      let a = "";
      sets.forEach(function (s) {
        if (s.id === "similar") return;
        if (flags[s.id].checked) a += s.chars;
      });
      if (flags.similar.checked) a += sets[4].chars;
      return a;
    }
    function gen() {
      try {
        const n = Math.min(128, Math.max(4, Number(len.value) || 20));
        const a = alphabet();
        const lines = [];
        for (let i = 0; i < 8; i++) lines.push(randomString(n, a));
        setText(out, lines.join("\n"));
        const bits = entropyBits(n, a.length).toFixed(1);
        setStatus(st, "8 secrets · ~" + bits + " bits each · generated with crypto.getRandomValues", "ok");
      } catch (e) { setStatus(st, e.message, "err"); }
    }
    gen();
    root.append(
      labelWrap("Length", len),
      boxes,
      row([
        btn("Generate", "", gen),
        btn("Copy first", "secondary", function () {
          copyOut((out.textContent.split("\n")[0] || ""), this);
        })
      ]),
      st, out
    );
  }

  function mountHtml(root) {
    const inn = textarea("html-in", "<div class=\"x\">Tom & Jerry</div>");
    const out = pre();
    const st = statusLine();
    root.append(
      labelWrap("Text", inn),
      row([
        btn("Encode", "", function () {
          setText(out, htmlEncode(inn.value));
          setStatus(st, "Encoded & < > \" '", "ok");
        }),
        btn("Decode", "secondary", function () {
          setText(out, htmlDecode(inn.value));
          setStatus(st, "Decoded named and numeric entities", "ok");
        }),
        btn("Copy", "secondary", function () { copyOut(out.textContent, this); })
      ]),
      st, out
    );
  }

  function mountCase(root) {
    const inn = textarea("case-in", "Work is God — public resume URL");
    const out = pre();
    const st = statusLine();
    function run() {
      const s = inn.value;
      setText(out, [
        "lower     " + s.toLowerCase(),
        "upper     " + s.toUpperCase(),
        "title     " + titleCase(s),
        "snake     " + snake(s),
        "kebab     " + kebab(s),
        "camel     " + camel(s),
        "pascal    " + pascal(s),
        "constant  " + constant(s),
        "slug      " + slugify(s)
      ].join("\n"));
      setStatus(st, "Converted", "ok");
    }
    root.append(
      labelWrap("Text", inn),
      row([
        btn("Convert", "", run),
        btn("Copy", "secondary", function () { copyOut(out.textContent, this); })
      ]),
      st, out
    );
  }

  function mountHex(root) {
    const inn = textarea("hex-in", "hello");
    const out = pre();
    const st = statusLine();
    root.append(
      labelWrap("Text or hex", inn),
      row([
        btn("To hex", "", function () {
          const b = bytesOf(inn.value);
          setText(out, toHex(b) + "\n\n" + hexDump(b));
          setStatus(st, b.length + " bytes", "ok");
        }),
        btn("From hex", "secondary", function () {
          try {
            const b = fromHex(inn.value);
            setText(out, fromBytes(b) + "\n\n" + hexDump(b));
            setStatus(st, b.length + " bytes", "ok");
          } catch (e) { setStatus(st, e.message, "err"); }
        }),
        btn("Copy", "secondary", function () { copyOut(out.textContent, this); })
      ]),
      st, out
    );
  }

  function mountBases(root) {
    const inn = input("base-in", "text", "255 or 0xff or 0b11111111", "255");
    const from = el("select", { id: "base-from" });
    [["auto", "Auto"], [10, "Decimal"], [16, "Hex"], [8, "Octal"], [2, "Binary"]].forEach(function (p) {
      from.appendChild(el("option", { value: String(p[0]), text: p[1] }));
    });
    const out = pre();
    const st = statusLine();
    function run() {
      try {
        const n = parseBase(inn.value, from.value === "auto" ? "auto" : Number(from.value));
        setText(out, [
          "dec  " + fmtBase(n, 10),
          "hex  " + fmtBase(n, 16),
          "oct  " + fmtBase(n, 8),
          "bin  " + fmtBase(n, 2)
        ].join("\n"));
        setStatus(st, "Converted with BigInt (arbitrary size)", "ok");
      } catch (e) { setStatus(st, e.message, "err"); }
    }
    run();
    root.append(
      labelWrap("Number", inn),
      labelWrap("From base", from),
      row([
        btn("Convert", "", run),
        btn("Copy", "secondary", function () { copyOut(out.textContent, this); })
      ]),
      st, out
    );
  }

  function mountPem(root) {
    const inn = textarea("pem-in", "-----BEGIN CERTIFICATE-----\n...\n-----END CERTIFICATE-----");
    const out = pre();
    const st = statusLine();
    async function run() {
      try {
        const blocks = await describePem(inn.value);
        const lines = [];
        blocks.forEach(function (b, i) {
          if (i) lines.push("");
          lines.push("type         " + b.type);
          lines.push("der bytes    " + b.bytes);
          lines.push("sha256       " + b.sha256);
          lines.push("fingerprint  " + b.fingerprint);
          b.details.forEach(function (d) { lines.push(d); });
        });
        setText(out, lines.join("\n"));
        setStatus(st, blocks.length + " PEM block(s) · parsed locally", "ok");
      } catch (e) { setStatus(st, e.message, "err"); }
    }
    root.append(
      labelWrap("PEM", inn, "Certificates, CSR, or public keys. Do not paste production private keys."),
      row([
        btn("Decode", "", run),
        btn("Copy", "secondary", function () { copyOut(out.textContent, this); })
      ]),
      st, out
    );
  }

  function mountCurl(root) {
    const url = input("curl-url", "url", "https://httpbin.org/get");
    const method = el("select", { id: "curl-method" });
    ["GET", "POST", "PUT", "PATCH", "DELETE", "HEAD", "OPTIONS"].forEach(function (m) {
      method.appendChild(el("option", { value: m, text: m }));
    });
    const headers = textarea("curl-headers", "Accept: application/json\nContent-Type: application/json");
    headers.classList.add("tool-in-short");
    const body = textarea("curl-body", '{\n  "ok": true\n}');
    body.classList.add("tool-in-short");
    const out = pre();
    const st = statusLine();
    function opts() {
      return {
        url: url.value.trim(),
        method: method.value,
        headers: headers.value,
        body: body.value
      };
    }
    function showCurl() {
      try {
        if (!opts().url) throw new Error("Add a URL.");
        setText(out, buildCurl(opts()));
        setStatus(st, "curl command · copy into a terminal", "ok");
      } catch (e) { setStatus(st, e.message, "err"); }
    }
    async function send() {
      const o = opts();
      if (!o.url) { setStatus(st, "Add a URL.", "err"); return; }
      let parsed;
      try { parsed = new URL(o.url); }
      catch (e) { setStatus(st, "URL is not valid.", "err"); return; }
      if (parsed.protocol !== "http:" && parsed.protocol !== "https:") {
        setStatus(st, "Only http and https.", "err");
        return;
      }
      const hdrs = {};
      String(o.headers).split(/\n/).forEach(function (line) {
        const t = line.trim();
        if (!t) return;
        const i = t.indexOf(":");
        if (i < 1) return;
        hdrs[t.slice(0, i).trim()] = t.slice(i + 1).trim();
      });
      const init = { method: o.method, headers: hdrs, credentials: "omit", redirect: "follow" };
      if (o.body && o.method !== "GET" && o.method !== "HEAD") init.body = o.body;
      setStatus(st, "Sending from this browser…", "");
      const t0 = performance.now();
      try {
        const res = await fetch(o.url, init);
        const ms = Math.round(performance.now() - t0);
        const text = await res.text();
        const hlines = [];
        res.headers.forEach(function (v, k) { hlines.push(k + ": " + v); });
        setText(out, [
          buildCurl(o),
          "",
          "HTTP " + res.status + " " + res.statusText + "  (" + ms + " ms)",
          "final URL  " + res.url,
          hlines.join("\n"),
          "",
          text
        ].join("\n"));
        setStatus(st, res.status + " " + (HTTP_CODES[res.status] || res.statusText) + " · CORS-visible headers only", res.ok ? "ok" : "err");
      } catch (e) {
        setText(out, buildCurl(o) + "\n\nBrowser fetch failed: " + e.message +
          "\n\nMost APIs block browser calls (CORS). Copy the curl command and run it in a terminal.");
        setStatus(st, "Fetch blocked (usually CORS). Use the curl command.", "err");
      }
    }
    root.append(
      labelWrap("URL", url),
      labelWrap("Method", method),
      labelWrap("Headers (one Name: value per line)", headers),
      labelWrap("Body", body),
      row([
        btn("Build curl", "", showCurl),
        btn("Send from browser", "secondary", send),
        btn("Copy output", "secondary", function () { copyOut(out.textContent, this); })
      ]),
      st, out
    );
  }

  const MOUNTS = {
    json: mountJson,
    base64: mountBase64,
    url: mountUrl,
    hash: mountHash,
    uuid: mountUuid,
    time: mountTime,
    jwt: mountJwt,
    regex: mountRegex,
    diff: mountDiff,
    query: mountQuery,
    cron: mountCron,
    http: mountHttp,
    cidr: mountCidr,
    password: mountPassword,
    html: mountHtml,
    case: mountCase,
    hex: mountHex,
    bases: mountBases,
    pem: mountPem,
    curl: mountCurl
  };

  const nav = document.getElementById("tools-nav");
  const search = document.getElementById("tool-search");
  const panels = $all(".tool-panel");
  const links = $all("#tools-nav a");
  const built = {};

  function toolIdFromHash() {
    const id = (location.hash || "#json").replace(/^#/, "");
    return document.getElementById(id) ? id : "json";
  }

  function show(id) {
    panels.forEach(function (p) {
      const on = p.getAttribute("data-tool") === id;
      p.hidden = !on;
      p.classList.toggle("is-on", on);
      if (on && !built[id]) {
        const mount = p.querySelector("[data-mount]");
        const fn = MOUNTS[id];
        if (fn && mount) {
          fn(mount);
          built[id] = true;
        }
      }
    });
    links.forEach(function (a) {
      a.classList.toggle("is-on", a.getAttribute("data-tool") === id);
    });
    const active = document.getElementById(id);
    if (active && window.matchMedia("(max-width: 800px)").matches) {
      active.scrollIntoView({ block: "start" });
    }
  }

  if (nav) {
    nav.addEventListener("click", function (e) {
      const a = e.target.closest("a[data-tool]");
      if (!a) return;
      /* hashchange handler will show */
    });
  }
  window.addEventListener("hashchange", function () { show(toolIdFromHash()); });
  show(toolIdFromHash());

  if (search) {
    search.addEventListener("input", function () {
      const q = search.value.trim().toLowerCase();
      links.forEach(function (a) {
        const blob = (a.textContent || "").toLowerCase();
        a.hidden = q !== "" && blob.indexOf(q) < 0;
      });
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "/" && document.activeElement && document.activeElement.tagName !== "INPUT"
        && document.activeElement.tagName !== "TEXTAREA" && document.activeElement.tagName !== "SELECT") {
        e.preventDefault();
        search.focus();
      }
    });
  }
})();
