'use strict';
/*
* SimBrief APIv1 Javascript Functions
* For use with VA Dispatch systems
* By Derek Mayer - contact@simbrief.com
*
* Any individual wishing to make use of this class must first contact me
* to obtain a unique API key; without which it will be impossible to connect
* to the API.
*
* Any attempt to circumvent the API authorization, steal another
* developer's API key, hack, compromise, or gain unauthorized access to
* the SimBrief website or it's web systems, or bypass or allow others to bypass
* the SimBrief.com login screen will result in immediate revocation of the
* associated API key, and in serious situations, legal action at my discretion.
*/

/*
* Settings and initial variables
*/

let sbform = "sbapiform";
let sbworkerurl = "https://www.simbrief.com/ofp/ofp.loader.api.php";
let sbworkerid = 'SBworker';
let sbcallerid = 'SBcaller';
let sbworkerstyle = 'width=600,height=315';
let sbworker;
let SBloop;

let ofp_id;
let flight_id;
let aircraft_id;

let outputpage_save;
let outputpage_calc;
let fe_result;

let timestamp;
let api_code;


function simbriefsubmit(_flight_id, _aircraft_id, outputpage) {
  flight_id = _flight_id;
  aircraft_id = _aircraft_id;

  if (sbworker) {
    sbworker.close();
  }

  if (SBloop) {
    window.clearInterval(SBloop);
  }

  api_code = null;
  ofp_id = null;
  fe_result = null;
  timestamp = null;
  outputpage_save = null;
  outputpage_calc = null;

  console.log('Flight ID', _flight_id);
  console.log('Aircraft ID', _aircraft_id);
  console.log('Output Page', outputpage);

  do_simbriefsubmit(outputpage);
}


async function do_simbriefsubmit(outputpage) {

  //CATCH UNDEFINED OUTPUT PAGE, SET IT TO THE CURRENT PAGE

  if (!outputpage) {
    outputpage = location.href;
  }

  if (!timestamp) {
    timestamp = Math.round(+new Date() / 1000);
  }

  outputpage_save = outputpage;
  outputpage_calc = outputpage.replace("http://", "");

  if (!api_code) {
    const api_req = document.getElementsByName('orig')[0].value + document.getElementsByName('dest')[0].value + document.getElementsByName('type')[0].value + timestamp + outputpage_calc;

    let apiCodeResp;

    try {
      apiCodeResp = await phpvms.request({
        method: 'POST',
        url: '/simbrief/apicode',
        data: {
          api_req,
          flight_id,
          aircraft_id,
        }
      });
    } catch (e) {
      console.log('request error', e);
      return;
    }

    api_code = apiCodeResp.data.api_code;
    console.log('API code response: ', api_code);
  }

  //IF API_CODE IS SET, FINALIZE FORM

  var apiform = document.getElementById(sbform);
  apiform.setAttribute("method", "get");
  apiform.setAttribute("action", sbworkerurl);
  apiform.setAttribute("target", sbworkerid);

  var input = document.createElement("input");
  input.setAttribute("type", "hidden");
  input.setAttribute("name", "apicode");
  input.setAttribute("value", api_code);
  apiform.appendChild(input);

  var input = document.createElement("input");
  input.setAttribute("type", "hidden");
  input.setAttribute("name", "outputpage");
  input.setAttribute("value", outputpage_calc);
  apiform.appendChild(input);


  var input = document.createElement("input");
  input.setAttribute("type", "hidden");
  input.setAttribute("name", "timestamp");
  input.setAttribute("value", timestamp);
  apiform.appendChild(input);


  //LAUNCH FORM

  window.name = sbcallerid;
  LaunchSBworker();
  apiform.submit();

  //DETERMINE OFP_ID

  ofp_id = timestamp + '_' + md5(document.getElementsByName('orig')[0].value + document.getElementsByName('dest')[0].value + document.getElementsByName('type')[0].value);

  //LOOP TO DETECT WHEN THE WORKER PROCESS IS CLOSED
  SBloop = window.setInterval(checkSBworker, 500);
}

/*
* Other related functions
*/

function LaunchSBworker() {
  sbworker = window.open('about:blank', sbworkerid, sbworkerstyle)

  //TEST FOR POPUP BLOCKERS

  if (sbworker == null || typeof (sbworker) == 'undefined') {
    alert('Please disable your pop-up blocker to generate a flight plan!');
  } else {
    if (window.focus) {
      sbworker.focus();
    }
  }
}


function checkSBworker() {
  if (sbworker && sbworker.closed) {
    window.clearInterval(SBloop);
    Redirect_caller();
  }
}


async function Redirect_caller(nbAttemps = 0) {

  /*
  * First check that the file actually exists.
  * It might not if the window was closed before completion.
  *
  * An external PHP file is used so as to avoid any "Same
  * Origin" errors.
  */

  let apiCodeResp;

  try {
    apiCodeResp = await phpvms.request({
      method: 'GET',
      url: '/simbrief/check_ofp',
      params: {
        aircraft_id,
        flight_id,
        ofp_id,
      }
    });
  } catch (e) {
    console.log('request error', e);
    if (nbAttemps < 5) {
      setTimeout(function () {
        Redirect_caller(nbAttemps + 1);
      }, 1500);
    } else {
      alert(`There was an error while generating an OFP. Please try reloading the page or contact your administrator. Error : ${e}`)
    }

    return;
  }

  api_code = apiCodeResp.data.id;
  console.log('API code response: ', api_code);

  /*
  * If the file exists, redirect to the specified Output Page.
  */
  outputpage_save += '/' + ofp_id;

  let apiform = document.createElement("form");
  apiform.setAttribute("method", "get");
  apiform.setAttribute("action", outputpage_save);

  /*
  * Analyse link to see if there are any prior GET params.
  * If so, append them to the form
  */

  let urlinfo = urlObject({'url': outputpage_save});
  for (let key in urlinfo['parameters']) {
    let input = document.createElement("input");
    input.setAttribute("type", "hidden");
    input.setAttribute("name", key);
    input.setAttribute("value", urlinfo['parameters'][key]);
    apiform.appendChild(input);
  }

  let input = document.createElement("input");
  input.setAttribute("type", "hidden");
  input.setAttribute("name", "ofp_id");
  input.setAttribute("value", ofp_id);
  apiform.appendChild(input);

  document.body.appendChild(apiform);

  apiform.submit();
}


function sb_res_load(url) {
  var fileref = document.createElement('script');
  fileref.type = "text/javascript";
  fileref.src = url + "&p=" + Math.floor(Math.random() * 10000000);
  document.getElementsByTagName("head")[0].appendChild(fileref);
}


/*
* URLOBJECT function
* Courtesy Ayman Farhat
*/

function urlObject(options) {
  "use strict";
  /*global window, document*/

  var url_search_arr,
    option_key,
    i,
    urlObj,
    get_param,
    key,
    val,
    url_query,
    url_get_params = {},
    a = document.createElement('a'),
    default_options = {
      'url': window.location.href,
      'unescape': true,
      'convert_num': true
    };

  if (typeof options !== "object") {
    options = default_options;
  } else {
    for (option_key in default_options) {
      if (default_options.hasOwnProperty(option_key)) {
        if (options[option_key] === undefined) {
          options[option_key] = default_options[option_key];
        }
      }
    }
  }

  a.href = options.url;
  url_query = a.search.substring(1);
  url_search_arr = url_query.split('&');

  if (url_search_arr[0].length > 1) {
    for (i = 0; i < url_search_arr.length; i += 1) {
      get_param = url_search_arr[i].split("=");

      if (options.unescape) {
        key = decodeURI(get_param[0]);
        val = decodeURI(get_param[1]);
      } else {
        key = get_param[0];
        val = get_param[1];
      }

      if (options.convert_num) {
        if (val.match(/^\d+$/)) {
          val = parseInt(val, 10);
        } else if (val.match(/^\d+\.\d+$/)) {
          val = parseFloat(val);
        }
      }

      if (url_get_params[key] === undefined) {
        url_get_params[key] = val;
      } else if (typeof url_get_params[key] === "string") {
        url_get_params[key] = [url_get_params[key], val];
      } else {
        url_get_params[key].push(val);
      }

      get_param = [];
    }
  }

  urlObj = {
    protocol: a.protocol,
    hostname: a.hostname,
    host: a.host,
    port: a.port,
    hash: a.hash.substr(1),
    pathname: a.pathname,
    search: a.search,
    parameters: url_get_params
  };

  return urlObj;
}


/*
* MD5 and UTF8_ENCODE functions
* Courtesy of phpjs.org
*/

function md5(str) {
  //  discuss at: http://phpjs.org/functions/md5/
  // original by: Webtoolkit.info (http://www.webtoolkit.info/)
  // improved by: Michael White (http://getsprink.com)
  // improved by: Jack
  // improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  //    input by: Brett Zamir (http://brett-zamir.me)
  // bugfixed by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  //  depends on: utf8_encode
  //   example 1: md5('Kevin van Zonneveld');
  //   returns 1: '6e658d4bfcb59cc13f96c14450ac40b9'

  var xl;

  var rotateLeft = function (lValue, iShiftBits) {
    return (lValue << iShiftBits) | (lValue >>> (32 - iShiftBits));
  };

  var addUnsigned = function (lX, lY) {
    var lX4, lY4, lX8, lY8, lResult;
    lX8 = (lX & 0x80000000);
    lY8 = (lY & 0x80000000);
    lX4 = (lX & 0x40000000);
    lY4 = (lY & 0x40000000);
    lResult = (lX & 0x3FFFFFFF) + (lY & 0x3FFFFFFF);
    if (lX4 & lY4) {
      return (lResult ^ 0x80000000 ^ lX8 ^ lY8);
    }
    if (lX4 | lY4) {
      if (lResult & 0x40000000) {
        return (lResult ^ 0xC0000000 ^ lX8 ^ lY8);
      } else {
        return (lResult ^ 0x40000000 ^ lX8 ^ lY8);
      }
    } else {
      return (lResult ^ lX8 ^ lY8);
    }
  };

  var _F = function (x, y, z) {
    return (x & y) | ((~x) & z);
  };
  var _G = function (x, y, z) {
    return (x & z) | (y & (~z));
  };
  var _H = function (x, y, z) {
    return (x ^ y ^ z);
  };
  var _I = function (x, y, z) {
    return (y ^ (x | (~z)));
  };

  var _FF = function (a, b, c, d, x, s, ac) {
    a = addUnsigned(a, addUnsigned(addUnsigned(_F(b, c, d), x), ac));
    return addUnsigned(rotateLeft(a, s), b);
  };

  var _GG = function (a, b, c, d, x, s, ac) {
    a = addUnsigned(a, addUnsigned(addUnsigned(_G(b, c, d), x), ac));
    return addUnsigned(rotateLeft(a, s), b);
  };

  var _HH = function (a, b, c, d, x, s, ac) {
    a = addUnsigned(a, addUnsigned(addUnsigned(_H(b, c, d), x), ac));
    return addUnsigned(rotateLeft(a, s), b);
  };

  var _II = function (a, b, c, d, x, s, ac) {
    a = addUnsigned(a, addUnsigned(addUnsigned(_I(b, c, d), x), ac));
    return addUnsigned(rotateLeft(a, s), b);
  };

  var convertToWordArray = function (str) {
    var lWordCount;
    var lMessageLength = str.length;
    var lNumberOfWords_temp1 = lMessageLength + 8;
    var lNumberOfWords_temp2 = (lNumberOfWords_temp1 - (lNumberOfWords_temp1 % 64)) / 64;
    var lNumberOfWords = (lNumberOfWords_temp2 + 1) * 16;
    var lWordArray = new Array(lNumberOfWords - 1);
    var lBytePosition = 0;
    var lByteCount = 0;
    while (lByteCount < lMessageLength) {
      lWordCount = (lByteCount - (lByteCount % 4)) / 4;
      lBytePosition = (lByteCount % 4) * 8;
      lWordArray[lWordCount] = (lWordArray[lWordCount] | (str.charCodeAt(lByteCount) << lBytePosition));
      lByteCount++;
    }
    lWordCount = (lByteCount - (lByteCount % 4)) / 4;
    lBytePosition = (lByteCount % 4) * 8;
    lWordArray[lWordCount] = lWordArray[lWordCount] | (0x80 << lBytePosition);
    lWordArray[lNumberOfWords - 2] = lMessageLength << 3;
    lWordArray[lNumberOfWords - 1] = lMessageLength >>> 29;
    return lWordArray;
  };

  var wordToHex = function (lValue) {
    var wordToHexValue = '',
      wordToHexValue_temp = '',
      lByte, lCount;
    for (lCount = 0; lCount <= 3; lCount++) {
      lByte = (lValue >>> (lCount * 8)) & 255;
      wordToHexValue_temp = '0' + lByte.toString(16);
      wordToHexValue = wordToHexValue + wordToHexValue_temp.substr(wordToHexValue_temp.length - 2, 2);
    }
    return wordToHexValue;
  };

  var x = [],
    k, AA, BB, CC, DD, a, b, c, d, S11 = 7,
    S12 = 12,
    S13 = 17,
    S14 = 22,
    S21 = 5,
    S22 = 9,
    S23 = 14,
    S24 = 20,
    S31 = 4,
    S32 = 11,
    S33 = 16,
    S34 = 23,
    S41 = 6,
    S42 = 10,
    S43 = 15,
    S44 = 21;

  str = utf8_encode(str);
  x = convertToWordArray(str);
  a = 0x67452301;
  b = 0xEFCDAB89;
  c = 0x98BADCFE;
  d = 0x10325476;

  xl = x.length;
  for (k = 0; k < xl; k += 16) {
    AA = a;
    BB = b;
    CC = c;
    DD = d;
    a = _FF(a, b, c, d, x[k + 0], S11, 0xD76AA478);
    d = _FF(d, a, b, c, x[k + 1], S12, 0xE8C7B756);
    c = _FF(c, d, a, b, x[k + 2], S13, 0x242070DB);
    b = _FF(b, c, d, a, x[k + 3], S14, 0xC1BDCEEE);
    a = _FF(a, b, c, d, x[k + 4], S11, 0xF57C0FAF);
    d = _FF(d, a, b, c, x[k + 5], S12, 0x4787C62A);
    c = _FF(c, d, a, b, x[k + 6], S13, 0xA8304613);
    b = _FF(b, c, d, a, x[k + 7], S14, 0xFD469501);
    a = _FF(a, b, c, d, x[k + 8], S11, 0x698098D8);
    d = _FF(d, a, b, c, x[k + 9], S12, 0x8B44F7AF);
    c = _FF(c, d, a, b, x[k + 10], S13, 0xFFFF5BB1);
    b = _FF(b, c, d, a, x[k + 11], S14, 0x895CD7BE);
    a = _FF(a, b, c, d, x[k + 12], S11, 0x6B901122);
    d = _FF(d, a, b, c, x[k + 13], S12, 0xFD987193);
    c = _FF(c, d, a, b, x[k + 14], S13, 0xA679438E);
    b = _FF(b, c, d, a, x[k + 15], S14, 0x49B40821);
    a = _GG(a, b, c, d, x[k + 1], S21, 0xF61E2562);
    d = _GG(d, a, b, c, x[k + 6], S22, 0xC040B340);
    c = _GG(c, d, a, b, x[k + 11], S23, 0x265E5A51);
    b = _GG(b, c, d, a, x[k + 0], S24, 0xE9B6C7AA);
    a = _GG(a, b, c, d, x[k + 5], S21, 0xD62F105D);
    d = _GG(d, a, b, c, x[k + 10], S22, 0x2441453);
    c = _GG(c, d, a, b, x[k + 15], S23, 0xD8A1E681);
    b = _GG(b, c, d, a, x[k + 4], S24, 0xE7D3FBC8);
    a = _GG(a, b, c, d, x[k + 9], S21, 0x21E1CDE6);
    d = _GG(d, a, b, c, x[k + 14], S22, 0xC33707D6);
    c = _GG(c, d, a, b, x[k + 3], S23, 0xF4D50D87);
    b = _GG(b, c, d, a, x[k + 8], S24, 0x455A14ED);
    a = _GG(a, b, c, d, x[k + 13], S21, 0xA9E3E905);
    d = _GG(d, a, b, c, x[k + 2], S22, 0xFCEFA3F8);
    c = _GG(c, d, a, b, x[k + 7], S23, 0x676F02D9);
    b = _GG(b, c, d, a, x[k + 12], S24, 0x8D2A4C8A);
    a = _HH(a, b, c, d, x[k + 5], S31, 0xFFFA3942);
    d = _HH(d, a, b, c, x[k + 8], S32, 0x8771F681);
    c = _HH(c, d, a, b, x[k + 11], S33, 0x6D9D6122);
    b = _HH(b, c, d, a, x[k + 14], S34, 0xFDE5380C);
    a = _HH(a, b, c, d, x[k + 1], S31, 0xA4BEEA44);
    d = _HH(d, a, b, c, x[k + 4], S32, 0x4BDECFA9);
    c = _HH(c, d, a, b, x[k + 7], S33, 0xF6BB4B60);
    b = _HH(b, c, d, a, x[k + 10], S34, 0xBEBFBC70);
    a = _HH(a, b, c, d, x[k + 13], S31, 0x289B7EC6);
    d = _HH(d, a, b, c, x[k + 0], S32, 0xEAA127FA);
    c = _HH(c, d, a, b, x[k + 3], S33, 0xD4EF3085);
    b = _HH(b, c, d, a, x[k + 6], S34, 0x4881D05);
    a = _HH(a, b, c, d, x[k + 9], S31, 0xD9D4D039);
    d = _HH(d, a, b, c, x[k + 12], S32, 0xE6DB99E5);
    c = _HH(c, d, a, b, x[k + 15], S33, 0x1FA27CF8);
    b = _HH(b, c, d, a, x[k + 2], S34, 0xC4AC5665);
    a = _II(a, b, c, d, x[k + 0], S41, 0xF4292244);
    d = _II(d, a, b, c, x[k + 7], S42, 0x432AFF97);
    c = _II(c, d, a, b, x[k + 14], S43, 0xAB9423A7);
    b = _II(b, c, d, a, x[k + 5], S44, 0xFC93A039);
    a = _II(a, b, c, d, x[k + 12], S41, 0x655B59C3);
    d = _II(d, a, b, c, x[k + 3], S42, 0x8F0CCC92);
    c = _II(c, d, a, b, x[k + 10], S43, 0xFFEFF47D);
    b = _II(b, c, d, a, x[k + 1], S44, 0x85845DD1);
    a = _II(a, b, c, d, x[k + 8], S41, 0x6FA87E4F);
    d = _II(d, a, b, c, x[k + 15], S42, 0xFE2CE6E0);
    c = _II(c, d, a, b, x[k + 6], S43, 0xA3014314);
    b = _II(b, c, d, a, x[k + 13], S44, 0x4E0811A1);
    a = _II(a, b, c, d, x[k + 4], S41, 0xF7537E82);
    d = _II(d, a, b, c, x[k + 11], S42, 0xBD3AF235);
    c = _II(c, d, a, b, x[k + 2], S43, 0x2AD7D2BB);
    b = _II(b, c, d, a, x[k + 9], S44, 0xEB86D391);
    a = addUnsigned(a, AA);
    b = addUnsigned(b, BB);
    c = addUnsigned(c, CC);
    d = addUnsigned(d, DD);
  }

  var temp = wordToHex(a) + wordToHex(b) + wordToHex(c) + wordToHex(d);

  return temp.toUpperCase().substr(0, 10);
}


function utf8_encode(argString) {
  //  discuss at: http://phpjs.org/functions/utf8_encode/
  // original by: Webtoolkit.info (http://www.webtoolkit.info/)
  // improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // improved by: sowberry
  // improved by: Jack
  // improved by: Yves Sucaet
  // improved by: kirilloid
  // bugfixed by: Onno Marsman
  // bugfixed by: Onno Marsman
  // bugfixed by: Ulrich
  // bugfixed by: Rafal Kukawski
  // bugfixed by: kirilloid
  //   example 1: utf8_encode('Kevin van Zonneveld');
  //   returns 1: 'Kevin van Zonneveld'

  if (argString === null || typeof argString === 'undefined') {
    return '';
  }

  // .replace(/\r\n/g, "\n").replace(/\r/g, "\n");
  var string = (argString + '');
  var utftext = '',
    start, end, stringl = 0;

  start = end = 0;
  stringl = string.length;
  for (var n = 0; n < stringl; n++) {
    var c1 = string.charCodeAt(n);
    var enc = null;

    if (c1 < 128) {
      end++;
    } else if (c1 > 127 && c1 < 2048) {
      enc = String.fromCharCode(
        (c1 >> 6) | 192, (c1 & 63) | 128
      );
    } else if ((c1 & 0xF800) != 0xD800) {
      enc = String.fromCharCode(
        (c1 >> 12) | 224, ((c1 >> 6) & 63) | 128, (c1 & 63) | 128
      );
    } else {
      // surrogate pairs
      if ((c1 & 0xFC00) != 0xD800) {
        throw new RangeError('Unmatched trail surrogate at ' + n);
      }
      var c2 = string.charCodeAt(++n);
      if ((c2 & 0xFC00) != 0xDC00) {
        throw new RangeError('Unmatched lead surrogate at ' + (n - 1));
      }
      c1 = ((c1 & 0x3FF) << 10) + (c2 & 0x3FF) + 0x10000;
      enc = String.fromCharCode(
        (c1 >> 18) | 240, ((c1 >> 12) & 63) | 128, ((c1 >> 6) & 63) | 128, (c1 & 63) | 128
      );
    }
    if (enc !== null) {
      if (end > start) {
        utftext += string.slice(start, end);
      }
      utftext += enc;
      start = end = n + 1;
    }
  }

  if (end > start) {
    utftext += string.slice(start, stringl);
  }

  return utftext;
}
