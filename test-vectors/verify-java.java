// ECPay API Test Vector Verification — Java
//
// Compile and run from repo root:
//   javac test-vectors/verify-java.java -d test-vectors && java -cp test-vectors VerifyJava
//
// Standard library only. Requires JDK 11+.
//
// ECPay-specific encoding notes:
//   - ecpayUrlEncode  : URLEncoder.encode() → replace ~ with %7E → toLowerCase → .NET restore
//   - aesUrlEncode    : URLEncoder.encode() → replace ~ with %7E  (no lowercase, no .NET restore)
//   - URLEncoder.encode() maps space to "+"  — matches PHP urlencode() (CORRECT for ECPay)
//   - URLEncoder.encode() does NOT encode '*' — must replace %2a→* manually after toLowerCase
//     (but since * is in our .NET restore list, it is handled there)
//   - AES key and IV: UTF-8 bytes, first 16 bytes only (AES-128)
//   - PKCS7 padding: if len % 16 == 0, padLen = 16 (add a full extra block)

import javax.crypto.Cipher;
import javax.crypto.spec.GCMParameterSpec;
import javax.crypto.spec.IvParameterSpec;
import javax.crypto.spec.SecretKeySpec;
import java.io.*;
import java.net.URLDecoder;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Paths;
import java.security.MessageDigest;
import java.util.*;

public class VerifyJava {

    // ──────────────────────────────────────────────
    // ecpayUrlEncode — used for CheckMacValue (CMV)
    //
    // Order of operations (mirrors PHP UrlService::ecpayUrlEncode):
    //   1. URLEncoder.encode()  →  space becomes "+", standard percent-encoding
    //      NOTE: Java URLEncoder does NOT encode '*' — handled in step 4 .NET restore list
    //   2. Replace "~" with "%7E"  (Java does not encode ~ by default in some contexts;
    //      URLEncoder actually does encode it, but we normalise to uppercase %7E here)
    //   3. toLowerCase (entire string)
    //   4. .NET-style char restoration: un-encode chars .NET URLEncoder leaves literal
    //      Patterns are lowercase after step 3, so %2d, %5f, etc. match safely.
    // ──────────────────────────────────────────────
    static String ecpayUrlEncode(String s) {
        String encoded;
        try {
            encoded = URLEncoder.encode(s, StandardCharsets.UTF_8.name());
        } catch (UnsupportedEncodingException e) {
            throw new RuntimeException(e); // UTF-8 always supported
        }

        // Step 2: normalise ~ to %7E (URLEncoder.encode encodes ~ as %7E already,
        // but we do a replace to be explicit and consistent with other implementations)
        encoded = encoded.replace("~", "%7E");

        // Step 3: lowercase
        encoded = encoded.toLowerCase();

        // Step 4: .NET char restoration (undo percent-encoding for chars .NET leaves literal)
        // All patterns are lowercase after step 3.
        encoded = encoded
                .replace("%2d", "-")
                .replace("%5f", "_")
                .replace("%2e", ".")
                .replace("%21", "!")
                .replace("%2a", "*")
                .replace("%28", "(")
                .replace("%29", ")");

        return encoded;
    }

    // ──────────────────────────────────────────────
    // aesUrlEncode — used before AES encryption
    //
    // Identical to ecpayUrlEncode EXCEPT:
    //   - No toLowerCase step
    //   - No .NET char restoration
    // The ~ replacement uses uppercase %7E to match ECPay expectation.
    //
    // IMPORTANT — Java URLEncoder quirk:
    //   URLEncoder does NOT encode '*' (treats it as safe). For AES we need '*' → %2A.
    //   So we must do: .replace("*", "%2A") after the standard encode.
    // ──────────────────────────────────────────────
    static String aesUrlEncode(String s) {
        String encoded;
        try {
            encoded = URLEncoder.encode(s, StandardCharsets.UTF_8.name());
        } catch (UnsupportedEncodingException e) {
            throw new RuntimeException(e);
        }

        // Java URLEncoder does not encode '*'; ECPay AES encoding requires it to be %2A
        encoded = encoded.replace("*", "%2A");

        // Normalise ~ → %7E (uppercase, matching ECPay reference)
        encoded = encoded.replace("~", "%7E");

        return encoded;
    }

    // ──────────────────────────────────────────────
    // calcCheckMacValue — standard CMV (AIO payment, logistics, invoice callback)
    //
    // Algorithm:
    //   1. Sort params case-insensitively by key
    //   2. Build: HashKey={k}&{sorted_params}&HashIV={iv}
    //   3. ecpayUrlEncode the whole string
    //   4. SHA-256 or MD5, uppercase hex
    // ──────────────────────────────────────────────
    static String calcCheckMacValue(String hashKey, String hashIV, Map<String, String> params, String method) {
        List<String> keys = new ArrayList<>(params.keySet());
        keys.sort(String.CASE_INSENSITIVE_ORDER);

        StringBuilder sb = new StringBuilder("HashKey=").append(hashKey);
        for (String k : keys) {
            sb.append('&').append(k).append('=').append(params.get(k));
        }
        sb.append("&HashIV=").append(hashIV);

        String encoded = ecpayUrlEncode(sb.toString());

        try {
            MessageDigest md = MessageDigest.getInstance("SHA256".equalsIgnoreCase(method) ? "SHA-256" : "MD5");
            byte[] hash = md.digest(encoded.getBytes(StandardCharsets.UTF_8));
            return bytesToHexUpper(hash);
        } catch (Exception e) {
            throw new RuntimeException(e);
        }
    }

    // ──────────────────────────────────────────────
    // calcEcticketCMV — E-Ticket CMV (different formula, no param sorting)
    //
    // Algorithm (per official ECPay E-Ticket docs):
    //   1. Concatenate: hashKey + plaintext_json + hashIV
    //   2. aesUrlEncode the concatenated string
    //   3. toLowerCase
    //   4. SHA-256, uppercase hex
    // ──────────────────────────────────────────────
    static String calcEcticketCMV(String hashKey, String hashIV, String plaintextJson) {
        String raw = hashKey + plaintextJson + hashIV;
        String encoded = aesUrlEncode(raw).toLowerCase();
        try {
            MessageDigest md = MessageDigest.getInstance("SHA-256");
            byte[] hash = md.digest(encoded.getBytes(StandardCharsets.UTF_8));
            return bytesToHexUpper(hash);
        } catch (Exception e) {
            throw new RuntimeException(e);
        }
    }

    // ──────────────────────────────────────────────
    // pkcs7Pad — pad byte array to next 16-byte boundary using PKCS7.
    // If len is already a multiple of 16, add a full 16-byte block (padLen = 16).
    // ──────────────────────────────────────────────
    static byte[] pkcs7Pad(byte[] data) {
        int padLen = 16 - (data.length % 16);
        // When data.length % 16 == 0, padLen = 16 (adds a full extra block — correct)
        byte[] padded = new byte[data.length + padLen];
        System.arraycopy(data, 0, padded, 0, data.length);
        Arrays.fill(padded, data.length, padded.length, (byte) padLen);
        return padded;
    }

    // ──────────────────────────────────────────────
    // pkcs7Unpad — remove PKCS7 padding, throw on invalid padding.
    // ──────────────────────────────────────────────
    static byte[] pkcs7Unpad(byte[] data) {
        if (data.length == 0) throw new IllegalArgumentException("Empty data");
        int padLen = data[data.length - 1] & 0xFF;
        if (padLen < 1 || padLen > 16) {
            throw new IllegalArgumentException("Invalid PKCS7 pad length: " + padLen);
        }
        for (int i = data.length - padLen; i < data.length; i++) {
            if ((data[i] & 0xFF) != padLen) {
                throw new IllegalArgumentException("Invalid PKCS7 padding bytes");
            }
        }
        return Arrays.copyOf(data, data.length - padLen);
    }

    // ──────────────────────────────────────────────
    // aesEncrypt — ECPay AES-128-CBC encryption
    //
    // Flow:
    //   1. aesUrlEncode(plaintextJson)
    //   2. PKCS7 pad the UTF-8 bytes
    //   3. AES-128-CBC with key[:16] and IV[:16] (UTF-8 bytes)
    //   4. Standard Base64 encode (not URL-safe)
    //
    // Returns {base64, urlEncoded} as a String[2].
    // ──────────────────────────────────────────────
    static String[] aesEncrypt(String plaintextJson, String hashKey, String hashIV) throws Exception {
        String urlEncoded = aesUrlEncode(plaintextJson);
        byte[] padded = pkcs7Pad(urlEncoded.getBytes(StandardCharsets.UTF_8));

        byte[] keyBytes = hashKey.getBytes(StandardCharsets.UTF_8);
        byte[] ivBytes  = hashIV.getBytes(StandardCharsets.UTF_8);
        SecretKeySpec keySpec = new SecretKeySpec(Arrays.copyOf(keyBytes, 16), "AES");
        IvParameterSpec ivSpec = new IvParameterSpec(Arrays.copyOf(ivBytes, 16));

        Cipher cipher = Cipher.getInstance("AES/CBC/NoPadding");
        cipher.init(Cipher.ENCRYPT_MODE, keySpec, ivSpec);
        byte[] ciphertext = cipher.doFinal(padded);

        return new String[]{ Base64.getEncoder().encodeToString(ciphertext), urlEncoded };
    }

    // ──────────────────────────────────────────────
    // aesDecrypt — ECPay AES-128-CBC decryption
    //
    // Flow:
    //   1. Standard Base64 decode
    //   2. AES-128-CBC decrypt with key[:16] and IV[:16]
    //   3. Remove PKCS7 padding
    //   4. Return the raw UTF-8 string (still URL-encoded at this point)
    //
    // The caller is responsible for the final URL-decode step (URLDecoder.decode).
    // This matches the Python reference: aes_decrypt() returns the URL-encoded string.
    // ──────────────────────────────────────────────
    static String aesDecrypt(String encryptedB64, String hashKey, String hashIV) throws Exception {
        byte[] ciphertext = Base64.getDecoder().decode(encryptedB64);

        byte[] keyBytes = hashKey.getBytes(StandardCharsets.UTF_8);
        byte[] ivBytes  = hashIV.getBytes(StandardCharsets.UTF_8);
        SecretKeySpec keySpec = new SecretKeySpec(Arrays.copyOf(keyBytes, 16), "AES");
        IvParameterSpec ivSpec = new IvParameterSpec(Arrays.copyOf(ivBytes, 16));

        Cipher cipher = Cipher.getInstance("AES/CBC/NoPadding");
        cipher.init(Cipher.DECRYPT_MODE, keySpec, ivSpec);
        byte[] plaintext = cipher.doFinal(ciphertext);

        byte[] unpadded = pkcs7Unpad(plaintext);

        // Return the URL-encoded string as-is — caller applies URLDecoder.decode.
        // This matches the Python reference: aes_decrypt() returns the URL-encoded string;
        // the test runner then calls urllib.parse.unquote_plus() separately to get JSON.
        return new String(unpadded, StandardCharsets.UTF_8);
    }

    // ──────────────────────────────────────────────
    // aesGcmEncrypt — ECPay AES-128-GCM encryption (electronic receipt V3.0+)
    //
    // Flow:
    //   1. aesUrlEncode(plaintextJson)
    //   2. AES-128-GCM with key[:16] and 12-byte IV (no padding)
    //   3. Output: IV (12B) || Ciphertext || Tag (16B), then Base64
    //
    // Returns {base64, tagHex, urlEncoded} as a String[3].
    // ──────────────────────────────────────────────
    static String[] aesGcmEncrypt(String plaintextJson, String hashKey, String ivHex) throws Exception {
        String urlEncoded = aesUrlEncode(plaintextJson);
        byte[] keyBytes = hashKey.getBytes(StandardCharsets.UTF_8);
        SecretKeySpec keySpec = new SecretKeySpec(Arrays.copyOf(keyBytes, 16), "AES");
        byte[] iv = hexToBytes(ivHex);
        GCMParameterSpec gcmSpec = new GCMParameterSpec(128, iv); // 128-bit tag

        Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
        cipher.init(Cipher.ENCRYPT_MODE, keySpec, gcmSpec);
        byte[] out = cipher.doFinal(urlEncoded.getBytes(StandardCharsets.UTF_8));
        // Java doFinal(GCM) returns ciphertext||tag (tag is last 16 bytes)
        byte[] tag = Arrays.copyOfRange(out, out.length - 16, out.length);

        // Concat IV || ciphertext+tag
        byte[] combined = new byte[iv.length + out.length];
        System.arraycopy(iv, 0, combined, 0, iv.length);
        System.arraycopy(out, 0, combined, iv.length, out.length);

        return new String[]{
            Base64.getEncoder().encodeToString(combined),
            bytesToHexLower(tag),
            urlEncoded
        };
    }

    // ──────────────────────────────────────────────
    // aesGcmDecrypt — ECPay AES-128-GCM decryption
    //
    // Input: Base64(IV 12B || Ciphertext || Tag 16B)
    // Throws AEADBadTagException on tag mismatch (data tampered or wrong key).
    // ──────────────────────────────────────────────
    static String aesGcmDecrypt(String encryptedB64, String hashKey) throws Exception {
        byte[] raw = Base64.getDecoder().decode(encryptedB64);
        if (raw.length < 12 + 16) {
            throw new IllegalArgumentException("ciphertext too short: " + raw.length);
        }
        byte[] iv = Arrays.copyOfRange(raw, 0, 12);
        byte[] ctTag = Arrays.copyOfRange(raw, 12, raw.length);

        byte[] keyBytes = hashKey.getBytes(StandardCharsets.UTF_8);
        SecretKeySpec keySpec = new SecretKeySpec(Arrays.copyOf(keyBytes, 16), "AES");
        GCMParameterSpec gcmSpec = new GCMParameterSpec(128, iv);

        Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
        cipher.init(Cipher.DECRYPT_MODE, keySpec, gcmSpec);
        byte[] plaintext = cipher.doFinal(ctTag);
        return new String(plaintext, StandardCharsets.UTF_8);
    }

    // ──────────────────────────────────────────────
    // Utility: hex string → byte array (for GCM IV)
    // ──────────────────────────────────────────────
    static byte[] hexToBytes(String hex) {
        int len = hex.length();
        byte[] bytes = new byte[len / 2];
        for (int i = 0; i < len; i += 2) {
            bytes[i / 2] = (byte) ((Character.digit(hex.charAt(i), 16) << 4)
                                 + Character.digit(hex.charAt(i + 1), 16));
        }
        return bytes;
    }

    // ──────────────────────────────────────────────
    // Utility: byte array → lowercase hex string (for GCM tag display)
    // ──────────────────────────────────────────────
    static String bytesToHexLower(byte[] bytes) {
        StringBuilder sb = new StringBuilder(bytes.length * 2);
        for (byte b : bytes) {
            sb.append(String.format("%02x", b & 0xFF));
        }
        return sb.toString();
    }

    // ──────────────────────────────────────────────
    // Utility: byte array → uppercase hex string
    // ──────────────────────────────────────────────
    static String bytesToHexUpper(byte[] bytes) {
        StringBuilder sb = new StringBuilder(bytes.length * 2);
        for (byte b : bytes) {
            sb.append(String.format("%02X", b & 0xFF));
        }
        return sb.toString();
    }

    // ──────────────────────────────────────────────
    // Minimal JSON parser — reads the three vector files without external deps.
    // Only handles the flat structures present in our test vectors.
    // ──────────────────────────────────────────────

    /** Extract a string value from a JSON object string by key. */
    static String jsonString(String json, String key) {
        String pattern = "\"" + key + "\"";
        int idx = json.indexOf(pattern);
        if (idx == -1) return null;
        idx += pattern.length();
        // skip whitespace and colon
        while (idx < json.length() && (json.charAt(idx) == ' ' || json.charAt(idx) == ':' || json.charAt(idx) == '\t' || json.charAt(idx) == '\n' || json.charAt(idx) == '\r')) idx++;
        if (idx >= json.length()) return null;
        char c = json.charAt(idx);
        if (c == '"') {
            // string value — handle escape sequences
            StringBuilder sb = new StringBuilder();
            idx++;
            while (idx < json.length()) {
                char ch = json.charAt(idx);
                if (ch == '\\' && idx + 1 < json.length()) {
                    char next = json.charAt(idx + 1);
                    switch (next) {
                        case '"': sb.append('"'); idx += 2; break;
                        case '\\': sb.append('\\'); idx += 2; break;
                        case '/': sb.append('/'); idx += 2; break;
                        case 'n': sb.append('\n'); idx += 2; break;
                        case 'r': sb.append('\r'); idx += 2; break;
                        case 't': sb.append('\t'); idx += 2; break;
                        case 'u': {
                            // \uXXXX
                            String hex = json.substring(idx + 2, Math.min(idx + 6, json.length()));
                            sb.append((char) Integer.parseInt(hex, 16));
                            idx += 6;
                            break;
                        }
                        default: sb.append(next); idx += 2; break;
                    }
                } else if (ch == '"') {
                    break;
                } else {
                    sb.append(ch);
                    idx++;
                }
            }
            return sb.toString();
        } else if (c == 'n' && json.startsWith("null", idx)) {
            return null;
        }
        // number or boolean (not needed here)
        return null;
    }

    /** Extract an integer value from a JSON object string by key. Returns -1 if absent. */
    static int jsonInt(String json, String key) {
        String pattern = "\"" + key + "\"";
        int idx = json.indexOf(pattern);
        if (idx == -1) return -1;
        idx += pattern.length();
        while (idx < json.length() && (json.charAt(idx) == ' ' || json.charAt(idx) == ':' || json.charAt(idx) == '\t' || json.charAt(idx) == '\n' || json.charAt(idx) == '\r')) idx++;
        if (idx >= json.length()) return -1;
        StringBuilder sb = new StringBuilder();
        while (idx < json.length() && Character.isDigit(json.charAt(idx))) {
            sb.append(json.charAt(idx++));
        }
        return sb.length() > 0 ? Integer.parseInt(sb.toString()) : -1;
    }

    /**
     * Split the top-level "vectors" array in the JSON file into individual JSON object strings.
     * Works by tracking brace depth to find object boundaries.
     */
    static List<String> splitVectors(String json) {
        List<String> result = new ArrayList<>();
        int arrStart = json.indexOf("\"vectors\"");
        if (arrStart == -1) return result;
        int openBracket = json.indexOf('[', arrStart);
        if (openBracket == -1) return result;
        int depth = 0;
        int objStart = -1;
        for (int i = openBracket; i < json.length(); i++) {
            char c = json.charAt(i);
            if (c == '{') {
                if (depth == 0) objStart = i;
                depth++;
            } else if (c == '}') {
                depth--;
                if (depth == 0 && objStart != -1) {
                    result.add(json.substring(objStart, i + 1));
                    objStart = -1;
                }
            }
        }
        return result;
    }

    /** Parse the "params" object inside a CMV vector JSON string into a Map. */
    static Map<String, String> parseParams(String vectorJson) {
        Map<String, String> map = new LinkedHashMap<>();
        int paramsIdx = vectorJson.indexOf("\"params\"");
        if (paramsIdx == -1) return map;
        int openBrace = vectorJson.indexOf('{', paramsIdx + 8);
        if (openBrace == -1) return map;
        // Extract the params {...} block
        int depth = 0, start = openBrace, end = openBrace;
        for (int i = openBrace; i < vectorJson.length(); i++) {
            char c = vectorJson.charAt(i);
            if (c == '{') depth++;
            else if (c == '}') { depth--; if (depth == 0) { end = i; break; } }
        }
        String paramsBlock = vectorJson.substring(start + 1, end);
        // Parse key-value pairs (simple string:string pairs)
        int i = 0;
        while (i < paramsBlock.length()) {
            // find next key
            int q1 = paramsBlock.indexOf('"', i);
            if (q1 == -1) break;
            int q2 = paramsBlock.indexOf('"', q1 + 1);
            if (q2 == -1) break;
            String key = paramsBlock.substring(q1 + 1, q2);
            // find colon
            int colon = paramsBlock.indexOf(':', q2 + 1);
            if (colon == -1) break;
            // find value start
            int vs = colon + 1;
            while (vs < paramsBlock.length() && (paramsBlock.charAt(vs) == ' ' || paramsBlock.charAt(vs) == '\n' || paramsBlock.charAt(vs) == '\r' || paramsBlock.charAt(vs) == '\t')) vs++;
            if (vs >= paramsBlock.length()) break;
            if (paramsBlock.charAt(vs) == '"') {
                // string value — handle escapes
                StringBuilder sb = new StringBuilder();
                int j = vs + 1;
                while (j < paramsBlock.length()) {
                    char c = paramsBlock.charAt(j);
                    if (c == '\\' && j + 1 < paramsBlock.length()) {
                        char next = paramsBlock.charAt(j + 1);
                        if (next == '"') { sb.append('"'); j += 2; }
                        else if (next == '\\') { sb.append('\\'); j += 2; }
                        else if (next == '/') { sb.append('/'); j += 2; }
                        else if (next == 'n') { sb.append('\n'); j += 2; }
                        else if (next == 'r') { sb.append('\r'); j += 2; }
                        else if (next == 't') { sb.append('\t'); j += 2; }
                        else { sb.append(next); j += 2; }
                    } else if (c == '"') {
                        i = j + 1;
                        break;
                    } else {
                        sb.append(c);
                        j++;
                    }
                }
                map.put(key, sb.toString());
            } else {
                i = vs + 1;
            }
        }
        return map;
    }

    // ──────────────────────────────────────────────
    // Test runner state and helpers
    // ──────────────────────────────────────────────
    static int failures = 0;

    static void check(String label, String expected, String actual) {
        if (expected.equals(actual)) {
            System.out.println("    " + label + ": PASS");
        } else {
            failures++;
            System.out.println("    " + label + ": FAIL");
            System.out.println("      Expected: " + expected);
            System.out.println("      Got:      " + actual);
        }
    }

    static void checkInt(String label, int expected, int actual) {
        if (expected == actual) {
            System.out.println("    " + label + ": PASS");
        } else {
            failures++;
            System.out.println("    " + label + ": FAIL");
            System.out.println("      Expected: " + expected);
            System.out.println("      Got:      " + actual);
        }
    }

    // ──────────────────────────────────────────────
    // main
    // ──────────────────────────────────────────────
    public static void main(String[] args) throws Exception {
        // Verify we are running from the repo root
        String[] requiredFiles = {
            "test-vectors/checkmacvalue.json",
            "test-vectors/aes-encryption.json",
            "test-vectors/url-encode-comparison.json"
        };
        for (String f : requiredFiles) {
            if (!new File(f).exists()) {
                System.err.println("Error: " + f + " not found. Please run from ecpay-skill root directory.");
                System.exit(1);
            }
        }

        // ── CheckMacValue Vectors ──────────────────
        System.out.println("=".repeat(60));
        System.out.println("CheckMacValue Vectors");
        System.out.println("=".repeat(60));

        String cmvJson = new String(Files.readAllBytes(Paths.get("test-vectors/checkmacvalue.json")), StandardCharsets.UTF_8);
        List<String> cmvVectors = splitVectors(cmvJson);

        for (int i = 0; i < cmvVectors.size(); i++) {
            String v = cmvVectors.get(i);
            String name          = jsonString(v, "name");
            String method        = jsonString(v, "method");
            String hashKey       = jsonString(v, "hashKey");
            String hashIV        = jsonString(v, "hashIV");
            String formula       = jsonString(v, "formula");
            String plaintextJson = jsonString(v, "plaintext_json");
            String expected      = jsonString(v, "expected");
            String wrongPct20    = jsonString(v, "wrong_with_percent20");

            String result;
            if ("ecticket".equals(formula)) {
                result = calcEcticketCMV(hashKey, hashIV, plaintextJson);
            } else {
                Map<String, String> params = parseParams(v);
                result = calcCheckMacValue(hashKey, hashIV, params, method != null ? method : "SHA256");
            }

            String status = result.equals(expected) ? "PASS" : "FAIL";
            if (!result.equals(expected)) failures++;
            System.out.printf("  Vector %d: %s | %s%n", i + 1, status, name);
            if (!result.equals(expected)) {
                System.out.println("    Expected: " + expected);
                System.out.println("    Got:      " + result);
            }

            // Verify wrong_%20 diagnostic
            if (wrongPct20 != null) {
                Map<String, String> params = parseParams(v);
                List<String> keys = new ArrayList<>(params.keySet());
                keys.sort(String.CASE_INSENSITIVE_ORDER);
                StringBuilder sb = new StringBuilder("HashKey=").append(hashKey);
                for (String k : keys) sb.append('&').append(k).append('=').append(params.get(k));
                sb.append("&HashIV=").append(hashIV);
                String encodedCorrect = ecpayUrlEncode(sb.toString());
                String encodedWrong = encodedCorrect.replace("+", "%20");
                MessageDigest md = MessageDigest.getInstance("SHA-256");
                String wrongHash = bytesToHexUpper(md.digest(encodedWrong.getBytes(StandardCharsets.UTF_8)));
                check("wrong %20", wrongPct20, wrongHash);
            }
        }

        // ── AES Vectors ───────────────────────────
        System.out.println();
        System.out.println("=".repeat(60));
        System.out.println("AES Encryption/Decryption Vectors");
        System.out.println("=".repeat(60));

        String aesJson = new String(Files.readAllBytes(Paths.get("test-vectors/aes-encryption.json")), StandardCharsets.UTF_8);
        List<String> aesVectors = splitVectors(aesJson);

        for (int i = 0; i < aesVectors.size(); i++) {
            String v = aesVectors.get(i);
            String name             = jsonString(v, "name");
            String mode             = jsonString(v, "mode"); // null for CBC (default)
            String hashKey          = jsonString(v, "hashKey");
            String hashIV           = jsonString(v, "hashIV");
            String ivHex            = jsonString(v, "iv_hex"); // GCM only
            String direction        = jsonString(v, "direction");
            String plaintextJson    = jsonString(v, "plaintext_json");
            String encryptedB64     = jsonString(v, "encrypted_base64");
            String expectedUrlEnc   = jsonString(v, "expected_url_encoded");
            int    expectedUrlLen   = jsonInt(v, "expected_url_encoded_length");
            String expectedBase64   = jsonString(v, "expected_base64");
            String expectedTagHex   = jsonString(v, "expected_tag_hex"); // GCM only
            String expectedDecrypted = jsonString(v, "expected_decrypted");
            String expectedJsonStr  = jsonString(v, "expected_json");

            if ("gcm".equals(mode)) {
                // ── AES-GCM branch (electronic receipt V3.0+) ──
                try {
                    if ("decrypt".equals(direction)) {
                        String result = aesGcmDecrypt(encryptedB64, hashKey);
                        String status = result.equals(expectedDecrypted) ? "PASS" : "FAIL";
                        if (!result.equals(expectedDecrypted)) failures++;
                        System.out.printf("  Vector %d: %s | %s%n", i + 1, status, name);
                        if (!result.equals(expectedDecrypted)) {
                            System.out.println("    Expected: " + expectedDecrypted);
                            System.out.println("    Got:      " + result);
                        }
                        String urlDecoded = URLDecoder.decode(result, StandardCharsets.UTF_8.name());
                        check("GCM URL decode -> JSON", expectedJsonStr, urlDecoded);
                    } else {
                        String[] encResult = aesGcmEncrypt(plaintextJson, hashKey, ivHex);
                        String b64 = encResult[0], tagHex = encResult[1], urlEncoded = encResult[2];
                        String status = b64.equals(expectedBase64) ? "PASS" : "FAIL";
                        if (!b64.equals(expectedBase64)) failures++;
                        System.out.printf("  Vector %d: %s | %s%n", i + 1, status, name);
                        if (!b64.equals(expectedBase64)) {
                            System.out.println("    Expected: " + expectedBase64);
                            System.out.println("    Got:      " + b64);
                        }
                        if (expectedTagHex != null) {
                            check("GCM tag", expectedTagHex, tagHex);
                        }
                        if (expectedUrlEnc != null) {
                            check("GCM URL encode", expectedUrlEnc, urlEncoded);
                        }
                        if (expectedUrlLen > 0) {
                            int actualLen = urlEncoded.getBytes(StandardCharsets.UTF_8).length;
                            checkInt("GCM URL encode length (" + actualLen + " bytes)", expectedUrlLen, actualLen);
                        }
                    }
                } catch (Exception ex) {
                    failures++;
                    System.out.printf("  Vector %d: FAIL | %s (error: %s)%n", i + 1, name, ex.getMessage());
                }
            } else if ("decrypt".equals(direction)) {
                String result = aesDecrypt(encryptedB64, hashKey, hashIV);
                String status = result.equals(expectedDecrypted) ? "PASS" : "FAIL";
                if (!result.equals(expectedDecrypted)) failures++;
                System.out.printf("  Vector %d: %s | %s%n", i + 1, status, name);
                if (!result.equals(expectedDecrypted)) {
                    System.out.println("    Expected: " + expectedDecrypted);
                    System.out.println("    Got:      " + result);
                }
                // Verify URL decode → JSON
                String urlDecoded = URLDecoder.decode(result, StandardCharsets.UTF_8.name());
                check("URL decode -> JSON", expectedJsonStr, urlDecoded);
            } else {
                String[] encResult = aesEncrypt(plaintextJson, hashKey, hashIV);
                String b64 = encResult[0], urlEncoded = encResult[1];
                String status = b64.equals(expectedBase64) ? "PASS" : "FAIL";
                if (!b64.equals(expectedBase64)) failures++;
                System.out.printf("  Vector %d: %s | %s%n", i + 1, status, name);
                if (!b64.equals(expectedBase64)) {
                    System.out.println("    Expected: " + expectedBase64);
                    System.out.println("    Got:      " + b64);
                }
                if (expectedUrlEnc != null) {
                    check("URL encode", expectedUrlEnc, urlEncoded);
                }
                if (expectedUrlLen > 0) {
                    int actualLen = urlEncoded.getBytes(StandardCharsets.UTF_8).length;
                    checkInt("URL encode length (" + actualLen + " bytes)", expectedUrlLen, actualLen);
                }
            }
        }

        // ── URL Encode Comparison Vectors ─────────
        System.out.println();
        System.out.println("=".repeat(60));
        System.out.println("URL Encode Comparison Vectors");
        System.out.println("=".repeat(60));

        String ueJson = new String(Files.readAllBytes(Paths.get("test-vectors/url-encode-comparison.json")), StandardCharsets.UTF_8);
        List<String> ueVectors = splitVectors(ueJson);

        for (int i = 0; i < ueVectors.size(); i++) {
            String v = ueVectors.get(i);
            String input       = jsonString(v, "input");
            String expectedCMV = jsonString(v, "ecpayUrlEncode");
            String expectedAES = jsonString(v, "aesUrlEncode");

            String cmvResult = ecpayUrlEncode(input);
            String aesResult = aesUrlEncode(input);
            boolean cmvOK = cmvResult.equals(expectedCMV);
            boolean aesOK = aesResult.equals(expectedAES);
            String status = (cmvOK && aesOK) ? "PASS" : "FAIL";
            if (!cmvOK || !aesOK) failures++;
            System.out.printf("  Vector %d: %s | input: %s%n", i + 1, status, input);
            if (!cmvOK) {
                System.out.println("    CMV Expected: " + expectedCMV);
                System.out.println("    CMV Got:      " + cmvResult);
            }
            if (!aesOK) {
                System.out.println("    AES Expected: " + expectedAES);
                System.out.println("    AES Got:      " + aesResult);
            }
        }

        // ── Summary ───────────────────────────────
        System.out.println();
        System.out.println("=".repeat(60));
        int totalCMV = cmvVectors.size();
        int totalAES = aesVectors.size();
        int totalUE  = ueVectors.size();
        System.out.printf("Total: %d CMV + %d AES + %d URL encode = %d vectors%n",
                totalCMV, totalAES, totalUE, totalCMV + totalAES + totalUE);
        if (failures == 0) {
            System.out.println("ALL PASSED");
        } else {
            System.out.println("FAILURES: " + failures);
            System.exit(1);
        }
    }
}
