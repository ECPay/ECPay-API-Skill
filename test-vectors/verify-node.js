// ECPay API Test Vector Verification — Node.js
//
// Run from repo root:
//   node test-vectors/verify-node.js
//
// Zero external dependencies. Requires Node.js 16+.
//
// ECPay-specific encoding notes (Node.js is the MOST trap-prone language):
//   - encodeURIComponent does NOT encode: - _ . ~ ! ' ( ) *
//   - encodeURIComponent encodes space as %20 (not +)
//   - To match PHP urlencode(): must manually encode ! ' ( ) * and replace %20→+
//   - ~ stays unencoded by encodeURIComponent → must replace with %7E (matching PHP)
//   - AES key/IV: Buffer UTF-8 bytes, first 16 bytes only (AES-128)
//   - PKCS7 padding: Node's createCipheriv auto-pads by default (PKCS = PKCS7)
//   - JSON insertion order: Node.js Object preserves string-keyed insertion order (ES2015+)

'use strict';

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

// ──────────────────────────────────────────────
// phpUrlencode — produce output identical to PHP urlencode() / Python quote_plus()
//
// This is the base layer both ecpayUrlEncode and aesUrlEncode build on.
// ──────────────────────────────────────────────
function phpUrlencode(s) {
  return encodeURIComponent(String(s))
    // Node.js encodeURIComponent leaves these unencoded; PHP/Python encode them
    .replace(/!/g, '%21')
    .replace(/'/g, '%27')
    .replace(/\(/g, '%28')
    .replace(/\)/g, '%29')
    .replace(/\*/g, '%2A')
    // Node.js encodes space as %20; PHP urlencode / Python quote_plus use +
    .replace(/%20/g, '+');
  // Note: ~ is NOT replaced here. verify.py also leaves ~ alone at quote_plus stage
  // and adds the %7E replacement only inside ecpayUrlEncode / aesUrlEncode below.
}

// ──────────────────────────────────────────────
// ecpayUrlEncode — used for CheckMacValue (CMV)
//
// Matches verify.py (lines 25-33), verify-go.go (lines 41-64), and
// scripts/SDK_PHP/src/Services/UrlService.php::ecpayUrlEncode.
//
// Order of operations:
//   1. phpUrlencode (space→+, manual encode of !'()*)
//   2. Replace ~ with %7E (PHP urlencode encodes ~; Node/Python do not)
//   3. toLowerCase on the entire string (makes all hex digits lowercase)
//   4. .NET-style char restoration: un-encode chars that .NET URLEncoder keeps literal
//      (after lowercasing, patterns like %2d are safe to match)
// ──────────────────────────────────────────────
function ecpayUrlEncode(s) {
  let encoded = phpUrlencode(s);
  encoded = encoded.replace(/~/g, '%7E');
  encoded = encoded.toLowerCase();
  const netReplacements = [
    ['%2d', '-'],
    ['%5f', '_'],
    ['%2e', '.'],
    ['%21', '!'],
    ['%2a', '*'],
    ['%28', '('],
    ['%29', ')'],
  ];
  for (const [from, to] of netReplacements) {
    encoded = encoded.split(from).join(to);
  }
  return encoded;
}

// ──────────────────────────────────────────────
// aesUrlEncode — used before AES encryption
//
// Same as ecpayUrlEncode EXCEPT:
//   - No toLowerCase step
//   - No .NET char restoration
// The ~ replacement still applies (%7E to match ECPay expectation).
// ──────────────────────────────────────────────
function aesUrlEncode(s) {
  return phpUrlencode(s).replace(/~/g, '%7E');
}

// ──────────────────────────────────────────────
// phpUrldecode — inverse of phpUrlencode (for AES decrypt verification)
// Python uses urllib.parse.unquote_plus; Node's decodeURIComponent doesn't
// handle + as space, so we replace + → space first.
// ──────────────────────────────────────────────
function phpUrldecode(s) {
  return decodeURIComponent(String(s).replace(/\+/g, ' '));
}

// ──────────────────────────────────────────────
// calcCheckMacValue — standard CMV (AIO payment, logistics, invoice callback)
//
// Algorithm:
//   1. Sort params case-insensitively by key
//   2. Build: HashKey={k}&{sorted_params}&HashIV={iv}
//   3. ecpayUrlEncode the whole string
//   4. SHA256 or MD5, uppercase hex
// ──────────────────────────────────────────────
function calcCheckMacValue(hashKey, hashIV, params, method) {
  const keys = Object.keys(params).sort((a, b) => {
    const la = a.toLowerCase();
    const lb = b.toLowerCase();
    if (la < lb) return -1;
    if (la > lb) return 1;
    return 0;
  });
  const parts = keys.map((k) => `${k}=${params[k]}`);
  const raw = `HashKey=${hashKey}&${parts.join('&')}&HashIV=${hashIV}`;
  const encoded = ecpayUrlEncode(raw);
  const algo = (method || 'SHA256').toUpperCase() === 'MD5' ? 'md5' : 'sha256';
  return crypto.createHash(algo).update(encoded, 'utf8').digest('hex').toUpperCase();
}

// ──────────────────────────────────────────────
// calcEcticketCMV — E-Ticket CMV (different formula, no param sorting)
//
// Per official ECPay E-Ticket docs:
//   1. Concatenate: hashKey + plaintext_json + hashIV  (raw concat, no sorting)
//   2. aesUrlEncode the concatenated string
//   3. toLowerCase (E-Ticket applies lowercase AFTER URL encode, unlike CMV)
//   4. SHA256, uppercase hex
// ──────────────────────────────────────────────
function calcEcticketCMV(hashKey, hashIV, plaintextJson) {
  const raw = hashKey + plaintextJson + hashIV;
  const encoded = aesUrlEncode(raw).toLowerCase();
  return crypto.createHash('sha256').update(encoded, 'utf8').digest('hex').toUpperCase();
}

// ──────────────────────────────────────────────
// aesEncrypt — ECPay AES-128-CBC encryption
//
// Flow:
//   1. aesUrlEncode(plaintextJson)
//   2. PKCS7 pad the UTF-8 bytes (Node auto-pads by default for aes-128-cbc)
//   3. AES-128-CBC with key[:16] and IV[:16] (UTF-8 bytes)
//   4. Standard Base64 encode
//
// NOTE: The test vectors use plaintext_json as a pre-formed JSON STRING, so we
// don't parse/re-stringify. This preserves the exact byte sequence the vectors expect.
// ──────────────────────────────────────────────
function aesEncrypt(plaintextJson, hashKey, hashIV) {
  const urlEncoded = aesUrlEncode(plaintextJson);
  const key = Buffer.from(hashKey, 'utf8').subarray(0, 16);
  const iv = Buffer.from(hashIV, 'utf8').subarray(0, 16);
  const cipher = crypto.createCipheriv('aes-128-cbc', key, iv);
  cipher.setAutoPadding(true); // PKCS (= PKCS7) padding, default but explicit for clarity
  const encrypted = Buffer.concat([
    cipher.update(urlEncoded, 'utf8'),
    cipher.final(),
  ]);
  return { base64: encrypted.toString('base64'), urlEncoded };
}

// ──────────────────────────────────────────────
// aesDecrypt — ECPay AES-128-CBC decryption
//
// Flow:
//   1. Standard Base64 decode
//   2. AES-128-CBC decrypt with key[:16] and IV[:16]
//   3. Remove PKCS7 padding (auto)
//   4. Return raw UTF-8 string (still URL-encoded; caller applies phpUrldecode)
// ──────────────────────────────────────────────
function aesDecrypt(encryptedBase64, hashKey, hashIV) {
  const key = Buffer.from(hashKey, 'utf8').subarray(0, 16);
  const iv = Buffer.from(hashIV, 'utf8').subarray(0, 16);
  const decipher = crypto.createDecipheriv('aes-128-cbc', key, iv);
  decipher.setAutoPadding(true);
  const decrypted = Buffer.concat([
    decipher.update(Buffer.from(encryptedBase64, 'base64')),
    decipher.final(),
  ]);
  return decrypted.toString('utf8');
}

// ──────────────────────────────────────────────
// Test runner
// ──────────────────────────────────────────────

let failures = 0;

function check(label, expected, actual) {
  if (expected === actual) {
    console.log(`    ${label}: PASS`);
  } else {
    failures++;
    console.log(`    ${label}: FAIL`);
    console.log(`      Expected: ${expected}`);
    console.log(`      Got:      ${actual}`);
  }
}

function main() {
  // Verify we're running from the repo root
  const required = [
    'test-vectors/checkmacvalue.json',
    'test-vectors/aes-encryption.json',
    'test-vectors/url-encode-comparison.json',
  ];
  for (const f of required) {
    if (!fs.existsSync(f)) {
      console.error(`Error: ${f} not found. Please run from ecpay-skill root directory.`);
      process.exit(1);
    }
  }

  // ── CheckMacValue Vectors ──────────────────
  console.log('='.repeat(60));
  console.log('CheckMacValue Vectors');
  console.log('='.repeat(60));

  const cmvData = JSON.parse(fs.readFileSync('test-vectors/checkmacvalue.json', 'utf8'));
  cmvData.vectors.forEach((v, idx) => {
    const i = idx + 1;
    let result;
    if (v.formula === 'ecticket') {
      result = calcEcticketCMV(v.hashKey, v.hashIV, v.plaintext_json);
    } else {
      result = calcCheckMacValue(v.hashKey, v.hashIV, v.params, v.method);
    }
    const ok = result === v.expected;
    if (!ok) failures++;
    console.log(`  Vector ${i}: ${ok ? 'PASS' : 'FAIL'} | ${v.name}`);
    if (!ok) {
      console.log(`    Expected: ${v.expected}`);
      console.log(`    Got:      ${result}`);
    }

    // Optional: verify the wrong_with_percent20 diagnostic value
    if (v.wrong_with_percent20) {
      const keys = Object.keys(v.params).sort((a, b) => {
        const la = a.toLowerCase();
        const lb = b.toLowerCase();
        if (la < lb) return -1;
        if (la > lb) return 1;
        return 0;
      });
      const parts = keys.map((k) => `${k}=${v.params[k]}`);
      const raw = `HashKey=${v.hashKey}&${parts.join('&')}&HashIV=${v.hashIV}`;
      const encodedCorrect = ecpayUrlEncode(raw);
      const encodedWrong = encodedCorrect.split('+').join('%20');
      const wrongHash = crypto
        .createHash('sha256')
        .update(encodedWrong, 'utf8')
        .digest('hex')
        .toUpperCase();
      check('wrong %20', v.wrong_with_percent20, wrongHash);
    }
  });

  // ── AES Vectors ────────────────────────────
  console.log('');
  console.log('='.repeat(60));
  console.log('AES Encryption/Decryption Vectors');
  console.log('='.repeat(60));

  const aesData = JSON.parse(fs.readFileSync('test-vectors/aes-encryption.json', 'utf8'));
  aesData.vectors.forEach((v, idx) => {
    const i = idx + 1;
    const direction = v.direction || 'encrypt';

    if (direction === 'decrypt') {
      try {
        const result = aesDecrypt(v.encrypted_base64, v.hashKey, v.hashIV);
        const ok = result === v.expected_decrypted;
        if (!ok) failures++;
        console.log(`  Vector ${i}: ${ok ? 'PASS' : 'FAIL'} | ${v.name}`);
        if (!ok) {
          console.log(`    Expected: ${v.expected_decrypted}`);
          console.log(`    Got:      ${result}`);
        }
        // Also verify URL decode → JSON
        const urlDecoded = phpUrldecode(result);
        check('URL decode -> JSON', v.expected_json, urlDecoded);
      } catch (err) {
        failures++;
        console.log(`  Vector ${i}: FAIL | ${v.name} (error: ${err.message})`);
      }
    } else if (v.plaintext_json === undefined) {
      // Explanatory vector (no expected_base64) — print name, skip verification
      console.log(`  Vector ${i}: SKIP (explanatory) | ${v.name}`);
    } else {
      try {
        const { base64, urlEncoded } = aesEncrypt(v.plaintext_json, v.hashKey, v.hashIV);
        const ok = base64 === v.expected_base64;
        if (!ok) failures++;
        console.log(`  Vector ${i}: ${ok ? 'PASS' : 'FAIL'} | ${v.name}`);
        if (!ok) {
          console.log(`    Expected: ${v.expected_base64}`);
          console.log(`    Got:      ${base64}`);
        }
        if (v.expected_url_encoded !== undefined) {
          check('URL encode', v.expected_url_encoded, urlEncoded);
        }
        if (v.expected_url_encoded_length !== undefined) {
          const actualLen = Buffer.byteLength(urlEncoded, 'utf8');
          check(
            `URL encode length (${actualLen} bytes)`,
            String(v.expected_url_encoded_length),
            String(actualLen)
          );
        }
      } catch (err) {
        failures++;
        console.log(`  Vector ${i}: FAIL | ${v.name} (error: ${err.message})`);
      }
    }
  });

  // ── URL Encode Comparison Vectors ─────────
  console.log('');
  console.log('='.repeat(60));
  console.log('URL Encode Comparison Vectors');
  console.log('='.repeat(60));

  const ueData = JSON.parse(fs.readFileSync('test-vectors/url-encode-comparison.json', 'utf8'));
  ueData.vectors.forEach((v, idx) => {
    const i = idx + 1;
    const cmvResult = ecpayUrlEncode(v.input);
    const aesResult = aesUrlEncode(v.input);
    const cmvOk = cmvResult === v.ecpayUrlEncode;
    const aesOk = aesResult === v.aesUrlEncode;
    const ok = cmvOk && aesOk;
    if (!ok) failures++;
    console.log(`  Vector ${i}: ${ok ? 'PASS' : 'FAIL'} | input: ${v.input}`);
    if (!cmvOk) {
      console.log(`    CMV Expected: ${v.ecpayUrlEncode}`);
      console.log(`    CMV Got:      ${cmvResult}`);
    }
    if (!aesOk) {
      console.log(`    AES Expected: ${v.aesUrlEncode}`);
      console.log(`    AES Got:      ${aesResult}`);
    }
  });

  // ── Summary ───────────────────────────────
  console.log('');
  console.log('='.repeat(60));
  const totalCmv = cmvData.vectors.length;
  const totalAes = aesData.vectors.length;
  const totalUe = ueData.vectors.length;
  console.log(
    `Total: ${totalCmv} CMV + ${totalAes} AES + ${totalUe} URL encode = ${
      totalCmv + totalAes + totalUe
    } vectors`
  );
  if (failures === 0) {
    console.log('ALL PASSED');
  } else {
    console.log(`FAILURES: ${failures}`);
    process.exit(1);
  }
}

main();
