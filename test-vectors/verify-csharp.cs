// ECPay API Test Vector Verification — C#
//
// Option A — dotnet-script (recommended, no project file needed):
//   dotnet tool install -g dotnet-script          # one-time setup
//   dotnet script test-vectors/verify-csharp.cs   # run from repo root
//
// Option B — compile with csc (Mono or full .NET Framework, add -r:System.Web.dll):
//   csc test-vectors/verify-csharp.cs -out:verify-csharp.exe
//   verify-csharp.exe                             # run from repo root
//
// Option C — wrap in a minimal .csproj for dotnet run (no extra packages needed):
//   Copy this file as Program.cs in a new console project, then:
//   dotnet run from the ecpay-skill repo root.
//
// Standard library only (.NET 6+). No NuGet packages required.
//
// ECPay-specific encoding notes:
//   - ecpayUrlEncode  : PhpUrlEncode() → replace ~ with %7E → lowercase → .NET restore
//   - aesUrlEncode    : PhpUrlEncode() → replace ~ with %7E  (no lowercase, no restore)
//
//   C# WARNING: Uri.EscapeDataString() encodes space as "%20" NOT "+".
//   PHP urlencode() and ECPay expect "+" for spaces.
//   System.Web.HttpUtility.UrlEncode() is correct but requires System.Web which is
//   not available in .NET Core console apps without a framework reference.
//
//   This script implements PhpUrlEncode() that replicates PHP urlencode() behaviour:
//     - Unreserved chars (letters, digits, _ - . ~) are NOT encoded
//     - Space → "+"
//     - All other bytes → %XX (uppercase hex)
//   Note: PHP urlencode() DOES encode ~ even though RFC 3986 treats it as unreserved.
//   We handle this by including ~ in the chars that need %7E replacement.
//
//   AES key and IV: UTF-8 bytes, first 16 bytes only (AES-128).
//   PKCS7 padding: if len % 16 == 0, padLen = 16 (add a full extra block).

using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Net;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;

// ──────────────────────────────────────────────
// PhpUrlEncode — replicates PHP urlencode() behaviour in pure .NET
//
// PHP urlencode():
//   - Unreserved chars (A-Z a-z 0-9 _ - .) are passed through unchanged
//   - Space → "+"
//   - All other bytes (including ~ ! * ' ( ) etc.) → %XX uppercase hex
//
// Uri.EscapeDataString() in .NET leaves _ - . ~ ! * ' ( ) unencoded per RFC 3986,
// and maps space to %20 — both are wrong for ECPay. We therefore build our own.
// ──────────────────────────────────────────────
static string PhpUrlEncode(string s)
{
    var sb = new StringBuilder(s.Length * 3);
    foreach (byte b in Encoding.UTF8.GetBytes(s))
    {
        char c = (char)b;
        if ((c >= 'A' && c <= 'Z') || (c >= 'a' && c <= 'z') || (c >= '0' && c <= '9')
            || c == '_' || c == '-' || c == '.')
        {
            // PHP urlencode() leaves these unreserved chars as-is
            sb.Append(c);
        }
        else if (c == ' ')
        {
            sb.Append('+');
        }
        else
        {
            sb.Append('%');
            sb.Append(b.ToString("X2")); // uppercase hex
        }
    }
    return sb.ToString();
}

// ──────────────────────────────────────────────
// EcpayUrlEncode — used for CheckMacValue (CMV)
//
// Order of operations (mirrors PHP UrlService::ecpayUrlEncode):
//   1. PhpUrlEncode()  →  space becomes "+", all specials → %XX uppercase
//   2. Replace "~" with "%7E"  (PHP encodes ~; our PhpUrlEncode already does this via step 1)
//      The replace is a no-op here but kept for clarity and safety.
//   3. ToLower (entire string)
//   4. .NET-style char restoration: un-encode chars .NET URLEncoder leaves literal.
//      All hex is already lowercase after step 3, so %2d etc. match safely.
// ──────────────────────────────────────────────
static string EcpayUrlEncode(string s)
{
    string encoded = PhpUrlEncode(s);

    // Step 2: ensure ~ is %7E (PhpUrlEncode already encodes it, but be explicit)
    encoded = encoded.Replace("~", "%7E");

    // Step 3: lowercase (hex digits %7B → %7b, etc.)
    encoded = encoded.ToLower();

    // Step 4: .NET char restoration — un-encode chars .NET URLEncoder historically
    // leaves literal. All patterns are lowercase after step 3.
    encoded = encoded
        .Replace("%2d", "-")
        .Replace("%5f", "_")
        .Replace("%2e", ".")
        .Replace("%21", "!")
        .Replace("%2a", "*")
        .Replace("%28", "(")
        .Replace("%29", ")");

    return encoded;
}

// ──────────────────────────────────────────────
// AesUrlEncode — used before AES encryption
//
// Identical to EcpayUrlEncode EXCEPT:
//   - No ToLower step
//   - No .NET char restoration
// The ~ replacement produces uppercase %7E (PhpUrlEncode already does this).
// ──────────────────────────────────────────────
static string AesUrlEncode(string s)
{
    string encoded = PhpUrlEncode(s);
    // ~ was encoded as %7E by PhpUrlEncode; ensure uppercase (already is, but explicit)
    encoded = encoded.Replace("~", "%7E");
    return encoded;
}

// ──────────────────────────────────────────────
// CalcCheckMacValue — standard CMV (AIO payment, logistics, invoice callback)
//
// Algorithm:
//   1. Sort params case-insensitively by key
//   2. Build: HashKey={k}&{sorted_params}&HashIV={iv}
//   3. EcpayUrlEncode the whole string
//   4. SHA-256 or MD5, uppercase hex
// ──────────────────────────────────────────────
static string CalcCheckMacValue(string hashKey, string hashIV, Dictionary<string, string> parms, string method)
{
    var sortedKeys = parms.Keys.OrderBy(k => k, StringComparer.OrdinalIgnoreCase).ToList();

    var sb = new StringBuilder("HashKey=").Append(hashKey);
    foreach (var k in sortedKeys)
    {
        sb.Append('&').Append(k).Append('=').Append(parms[k]);
    }
    sb.Append("&HashIV=").Append(hashIV);

    string encoded = EcpayUrlEncode(sb.ToString());
    byte[] encodedBytes = Encoding.UTF8.GetBytes(encoded);

    byte[] hash;
    if (method.Equals("MD5", StringComparison.OrdinalIgnoreCase))
        hash = MD5.HashData(encodedBytes);
    else
        hash = SHA256.HashData(encodedBytes);

    return BytesToHexUpper(hash);
}

// ──────────────────────────────────────────────
// CalcEcticketCMV — E-Ticket CMV (different formula, no param sorting)
//
// Algorithm (per official ECPay E-Ticket docs):
//   1. Concatenate: hashKey + plaintext_json + hashIV
//   2. AesUrlEncode the concatenated string
//   3. ToLower
//   4. SHA-256, uppercase hex
// ──────────────────────────────────────────────
static string CalcEcticketCMV(string hashKey, string hashIV, string plaintextJson)
{
    string raw = hashKey + plaintextJson + hashIV;
    string encoded = AesUrlEncode(raw).ToLower();
    byte[] hash = SHA256.HashData(Encoding.UTF8.GetBytes(encoded));
    return BytesToHexUpper(hash);
}

// ──────────────────────────────────────────────
// Pkcs7Pad — pad byte array to next 16-byte boundary using PKCS7.
// If len is already a multiple of 16, add a full 16-byte block (padLen = 16).
// ──────────────────────────────────────────────
static byte[] Pkcs7Pad(byte[] data)
{
    int padLen = 16 - (data.Length % 16);
    // When data.Length % 16 == 0, padLen = 16 (adds a full extra block — correct)
    byte[] padded = new byte[data.Length + padLen];
    Array.Copy(data, padded, data.Length);
    for (int i = data.Length; i < padded.Length; i++)
        padded[i] = (byte)padLen;
    return padded;
}

// ──────────────────────────────────────────────
// Pkcs7Unpad — remove PKCS7 padding, throw on invalid padding.
// ──────────────────────────────────────────────
static byte[] Pkcs7Unpad(byte[] data)
{
    if (data.Length == 0) throw new InvalidOperationException("Empty data");
    int padLen = data[^1];
    if (padLen < 1 || padLen > 16)
        throw new InvalidOperationException($"Invalid PKCS7 pad length: {padLen}");
    for (int i = data.Length - padLen; i < data.Length; i++)
    {
        if (data[i] != padLen)
            throw new InvalidOperationException("Invalid PKCS7 padding bytes");
    }
    return data[..^padLen];
}

// ──────────────────────────────────────────────
// AesEncrypt — ECPay AES-128-CBC encryption
//
// Flow:
//   1. AesUrlEncode(plaintextJson)
//   2. PKCS7 pad the UTF-8 bytes
//   3. AES-128-CBC with key[:16] and IV[:16] (UTF-8 bytes)
//   4. Standard Base64 encode (not URL-safe)
//
// Returns (base64, urlEncoded).
// ──────────────────────────────────────────────
static (string Base64, string UrlEncoded) AesEncrypt(string plaintextJson, string hashKey, string hashIV)
{
    string urlEncoded = AesUrlEncode(plaintextJson);
    byte[] padded = Pkcs7Pad(Encoding.UTF8.GetBytes(urlEncoded));

    byte[] keyBytes = Encoding.UTF8.GetBytes(hashKey)[..16];
    byte[] ivBytes  = Encoding.UTF8.GetBytes(hashIV)[..16];

    using var aes = Aes.Create();
    aes.Key     = keyBytes;
    aes.IV      = ivBytes;
    aes.Mode    = CipherMode.CBC;
    aes.Padding = PaddingMode.None; // We apply PKCS7 manually

    using var encryptor = aes.CreateEncryptor();
    byte[] ciphertext = encryptor.TransformFinalBlock(padded, 0, padded.Length);
    return (Convert.ToBase64String(ciphertext), urlEncoded);
}

// ──────────────────────────────────────────────
// AesDecrypt — ECPay AES-128-CBC decryption
//
// Flow:
//   1. Standard Base64 decode
//   2. AES-128-CBC decrypt with key[:16] and IV[:16]
//   3. Remove PKCS7 padding
//   4. Return the raw UTF-8 string (which is still URL-encoded at this point)
//
// The caller is responsible for the final URL-decode step.
// This matches the Python reference: aes_decrypt() returns the URL-encoded string;
// the test runner then calls urllib.parse.unquote_plus() separately to get JSON.
// ──────────────────────────────────────────────
static string AesDecrypt(string encryptedB64, string hashKey, string hashIV)
{
    byte[] ciphertext = Convert.FromBase64String(encryptedB64);

    byte[] keyBytes = Encoding.UTF8.GetBytes(hashKey)[..16];
    byte[] ivBytes  = Encoding.UTF8.GetBytes(hashIV)[..16];

    using var aes = Aes.Create();
    aes.Key     = keyBytes;
    aes.IV      = ivBytes;
    aes.Mode    = CipherMode.CBC;
    aes.Padding = PaddingMode.None; // We strip PKCS7 manually

    using var decryptor = aes.CreateDecryptor();
    byte[] plaintext = decryptor.TransformFinalBlock(ciphertext, 0, ciphertext.Length);

    byte[] unpadded = Pkcs7Unpad(plaintext);


    // Return the URL-encoded string as-is — caller applies URL decode.
    return Encoding.UTF8.GetString(unpadded);
}

// ──────────────────────────────────────────────
// AesGcmEncrypt — ECPay AES-128-GCM encryption (electronic receipt V3.0+)
//
// Flow:
//   1. AesUrlEncode(plaintextJson)
//   2. AES-128-GCM with key[:16] and 12-byte IV (no padding)
//   3. Output: IV (12B) || Ciphertext || Tag (16B), then Base64
//
// Returns (base64, tagHex, urlEncoded).
// ──────────────────────────────────────────────
static (string Base64, string TagHex, string UrlEncoded) AesGcmEncrypt(string plaintextJson, string hashKey, string ivHex)
{
    string urlEncoded = AesUrlEncode(plaintextJson);
    byte[] keyBytes = Encoding.UTF8.GetBytes(hashKey)[..16];
    byte[] iv = Convert.FromHexString(ivHex);
    byte[] plaintextBytes = Encoding.UTF8.GetBytes(urlEncoded);
    byte[] ciphertext = new byte[plaintextBytes.Length];
    byte[] tag = new byte[16];

    // .NET 8+ requires tag size via constructor; .NET 6/7 uses default (16)
    using var aesGcm = new AesGcm(keyBytes, 16);
    aesGcm.Encrypt(iv, plaintextBytes, ciphertext, tag);

    // Concat IV || Ciphertext || Tag
    byte[] combined = new byte[iv.Length + ciphertext.Length + tag.Length];
    Buffer.BlockCopy(iv, 0, combined, 0, iv.Length);
    Buffer.BlockCopy(ciphertext, 0, combined, iv.Length, ciphertext.Length);
    Buffer.BlockCopy(tag, 0, combined, iv.Length + ciphertext.Length, tag.Length);

    return (Convert.ToBase64String(combined), Convert.ToHexString(tag).ToLowerInvariant(), urlEncoded);
}

// ──────────────────────────────────────────────
// AesGcmDecrypt — ECPay AES-128-GCM decryption
//
// Input: Base64(IV 12B || Ciphertext || Tag 16B)
// Throws CryptographicException on tag mismatch (data tampered or wrong key).
// ──────────────────────────────────────────────
static string AesGcmDecrypt(string encryptedB64, string hashKey)
{
    byte[] raw = Convert.FromBase64String(encryptedB64);
    if (raw.Length < 12 + 16)
        throw new ArgumentException($"ciphertext too short: {raw.Length}");

    byte[] iv = raw[..12];
    byte[] tag = raw[^16..];
    byte[] ciphertext = raw[12..^16];

    byte[] keyBytes = Encoding.UTF8.GetBytes(hashKey)[..16];
    byte[] plaintext = new byte[ciphertext.Length];

    using var aesGcm = new AesGcm(keyBytes, 16);
    aesGcm.Decrypt(iv, ciphertext, tag, plaintext); // throws on tag mismatch

    return Encoding.UTF8.GetString(plaintext);
}

// ──────────────────────────────────────────────
// Utility: byte array → uppercase hex string
// ──────────────────────────────────────────────
static string BytesToHexUpper(byte[] bytes)
{
    return Convert.ToHexString(bytes); // Always uppercase in .NET 5+
}

// ──────────────────────────────────────────────
// Test runner state and helpers
// ──────────────────────────────────────────────
int failures = 0;

void Check(string label, string expected, string actual)
{
    if (expected == actual)
    {
        Console.WriteLine($"    {label}: PASS");
    }
    else
    {
        failures++;
        Console.WriteLine($"    {label}: FAIL");
        Console.WriteLine($"      Expected: {expected}");
        Console.WriteLine($"      Got:      {actual}");
    }
}

void CheckInt(string label, int expected, int actual)
{
    if (expected == actual)
    {
        Console.WriteLine($"    {label}: PASS");
    }
    else
    {
        failures++;
        Console.WriteLine($"    {label}: FAIL");
        Console.WriteLine($"      Expected: {expected}");
        Console.WriteLine($"      Got:      {actual}");
    }
}

// ──────────────────────────────────────────────
// Verify working directory
// ──────────────────────────────────────────────
string[] requiredFiles = {
    "test-vectors/checkmacvalue.json",
    "test-vectors/aes-encryption.json",
    "test-vectors/url-encode-comparison.json"
};
foreach (var f in requiredFiles)
{
    if (!File.Exists(f))
    {
        Console.Error.WriteLine($"Error: {f} not found. Please run from ecpay-skill root directory.");
        Environment.Exit(1);
    }
}

// ── CheckMacValue Vectors ──────────────────────
Console.WriteLine(new string('=', 60));
Console.WriteLine("CheckMacValue Vectors");
Console.WriteLine(new string('=', 60));

string cmvJson = File.ReadAllText("test-vectors/checkmacvalue.json", Encoding.UTF8);
using var cmvDoc = JsonDocument.Parse(cmvJson);
var cmvVectors = cmvDoc.RootElement.GetProperty("vectors").EnumerateArray().ToList();

for (int i = 0; i < cmvVectors.Count; i++)
{
    var v        = cmvVectors[i];
    string name  = v.GetProperty("name").GetString()!;
    string hashKey = v.GetProperty("hashKey").GetString()!;
    string hashIV  = v.GetProperty("hashIV").GetString()!;
    string expected = v.GetProperty("expected").GetString()!;
    string formula = v.TryGetProperty("formula", out var fProp) ? fProp.GetString()! : "";
    string method  = v.TryGetProperty("method",  out var mProp) ? mProp.GetString()! : "SHA256";
    string wrongPct20 = v.TryGetProperty("wrong_with_percent20", out var wProp) ? wProp.GetString()! : "";

    string result;
    if (formula == "ecticket")
    {
        string plaintextJson = v.GetProperty("plaintext_json").GetString()!;
        result = CalcEcticketCMV(hashKey, hashIV, plaintextJson);
    }
    else
    {
        var parms = new Dictionary<string, string>();
        foreach (var kv in v.GetProperty("params").EnumerateObject())
            parms[kv.Name] = kv.Value.GetString()!;
        result = CalcCheckMacValue(hashKey, hashIV, parms, method);
    }

    string status = result == expected ? "PASS" : "FAIL";
    if (result != expected) failures++;
    Console.WriteLine($"  Vector {i + 1}: {status} | {name}");
    if (result != expected)
    {
        Console.WriteLine($"    Expected: {expected}");
        Console.WriteLine($"    Got:      {result}");
    }

    // Verify wrong_%20 diagnostic
    if (!string.IsNullOrEmpty(wrongPct20))
    {
        var parms = new Dictionary<string, string>();
        foreach (var kv in v.GetProperty("params").EnumerateObject())
            parms[kv.Name] = kv.Value.GetString()!;

        var sortedKeys = parms.Keys.OrderBy(k => k, StringComparer.OrdinalIgnoreCase).ToList();
        var sb = new StringBuilder("HashKey=").Append(hashKey);
        foreach (var k in sortedKeys) sb.Append('&').Append(k).Append('=').Append(parms[k]);
        sb.Append("&HashIV=").Append(hashIV);

        string encodedCorrect = EcpayUrlEncode(sb.ToString());
        string encodedWrong   = encodedCorrect.Replace("+", "%20");
        string wrongHash = BytesToHexUpper(SHA256.HashData(Encoding.UTF8.GetBytes(encodedWrong)));
        Check("wrong %20", wrongPct20, wrongHash);
    }
}

// ── AES Vectors ───────────────────────────────
Console.WriteLine();
Console.WriteLine(new string('=', 60));
Console.WriteLine("AES Encryption/Decryption Vectors");
Console.WriteLine(new string('=', 60));

string aesJson = File.ReadAllText("test-vectors/aes-encryption.json", Encoding.UTF8);
using var aesDoc = JsonDocument.Parse(aesJson);
var aesVectors = aesDoc.RootElement.GetProperty("vectors").EnumerateArray().ToList();

for (int i = 0; i < aesVectors.Count; i++)
{
    var v = aesVectors[i];
    string name    = v.GetProperty("name").GetString()!;
    string mode    = v.TryGetProperty("mode", out var mProp) ? mProp.GetString()! : "cbc";
    string hashKey = v.GetProperty("hashKey").GetString()!;
    string hashIV  = v.TryGetProperty("hashIV", out var hivProp) ? hivProp.GetString()! : "";
    string direction = v.TryGetProperty("direction", out var dProp) ? dProp.GetString()! : "encrypt";

    if (mode == "gcm")
    {
        // ── AES-GCM branch (electronic receipt V3.0+) ──
        try
        {
            if (direction == "decrypt")
            {
                string encryptedB64    = v.GetProperty("encrypted_base64").GetString()!;
                string expectedDecr    = v.GetProperty("expected_decrypted").GetString()!;
                string expectedJsonStr = v.GetProperty("expected_json").GetString()!;

                string result = AesGcmDecrypt(encryptedB64, hashKey);
                string status = result == expectedDecr ? "PASS" : "FAIL";
                if (result != expectedDecr) failures++;
                Console.WriteLine($"  Vector {i + 1}: {status} | {name}");
                if (result != expectedDecr)
                {
                    Console.WriteLine($"    Expected: {expectedDecr}");
                    Console.WriteLine($"    Got:      {result}");
                }
                string urlDecoded = WebUtility.UrlDecode(result);
                Check("GCM URL decode -> JSON", expectedJsonStr, urlDecoded);
            }
            else
            {
                string plaintextJson  = v.GetProperty("plaintext_json").GetString()!;
                string expectedBase64 = v.GetProperty("expected_base64").GetString()!;
                string ivHex          = v.GetProperty("iv_hex").GetString()!;
                string expectedTagHex = v.TryGetProperty("expected_tag_hex", out var tagProp) ? tagProp.GetString()! : "";
                string expectedUrlEnc = v.TryGetProperty("expected_url_encoded", out var euProp) ? euProp.GetString()! : "";
                int expectedUrlLen    = v.TryGetProperty("expected_url_encoded_length", out var elProp) ? elProp.GetInt32() : -1;

                var (b64, tagHex, urlEncoded) = AesGcmEncrypt(plaintextJson, hashKey, ivHex);
                string status = b64 == expectedBase64 ? "PASS" : "FAIL";
                if (b64 != expectedBase64) failures++;
                Console.WriteLine($"  Vector {i + 1}: {status} | {name}");
                if (b64 != expectedBase64)
                {
                    Console.WriteLine($"    Expected: {expectedBase64}");
                    Console.WriteLine($"    Got:      {b64}");
                }
                if (!string.IsNullOrEmpty(expectedTagHex))
                    Check("GCM tag", expectedTagHex, tagHex);
                if (!string.IsNullOrEmpty(expectedUrlEnc))
                    Check("GCM URL encode", expectedUrlEnc, urlEncoded);
                if (expectedUrlLen > 0)
                {
                    int actualLen = Encoding.UTF8.GetByteCount(urlEncoded);
                    CheckInt($"GCM URL encode length ({actualLen} bytes)", expectedUrlLen, actualLen);
                }
            }
        }
        catch (Exception ex)
        {
            failures++;
            Console.WriteLine($"  Vector {i + 1}: FAIL | {name} (error: {ex.Message})");
        }
    }
    else if (direction == "decrypt")
    {
        string encryptedB64    = v.GetProperty("encrypted_base64").GetString()!;
        string expectedDecr    = v.GetProperty("expected_decrypted").GetString()!;
        string expectedJsonStr = v.GetProperty("expected_json").GetString()!;

        // AesDecrypt returns the URL-encoded string (after removing PKCS7 padding).
        // expected_decrypted is the URL-encoded form (e.g. %7B%22MerchantID%22...).
        string result = AesDecrypt(encryptedB64, hashKey, hashIV);
        string status = result == expectedDecr ? "PASS" : "FAIL";
        if (result != expectedDecr) failures++;
        Console.WriteLine($"  Vector {i + 1}: {status} | {name}");
        if (result != expectedDecr)
        {
            Console.WriteLine($"    Expected: {expectedDecr}");
            Console.WriteLine($"    Got:      {result}");
        }
        // Verify URL decode → JSON
        // WebUtility.UrlDecode (System.Net) converts "+" → space and %XX sequences.
        string urlDecoded = WebUtility.UrlDecode(result);
        Check("URL decode -> JSON", expectedJsonStr, urlDecoded);
    }
    else
    {
        string plaintextJson  = v.GetProperty("plaintext_json").GetString()!;
        string expectedBase64 = v.GetProperty("expected_base64").GetString()!;
        string expectedUrlEnc = v.TryGetProperty("expected_url_encoded", out var euProp) ? euProp.GetString()! : "";
        int expectedUrlLen    = v.TryGetProperty("expected_url_encoded_length", out var elProp) ? elProp.GetInt32() : -1;

        var (b64, urlEncoded) = AesEncrypt(plaintextJson, hashKey, hashIV);
        string status = b64 == expectedBase64 ? "PASS" : "FAIL";
        if (b64 != expectedBase64) failures++;
        Console.WriteLine($"  Vector {i + 1}: {status} | {name}");
        if (b64 != expectedBase64)
        {
            Console.WriteLine($"    Expected: {expectedBase64}");
            Console.WriteLine($"    Got:      {b64}");
        }
        if (!string.IsNullOrEmpty(expectedUrlEnc))
        {
            Check("URL encode", expectedUrlEnc, urlEncoded);
        }
        if (expectedUrlLen > 0)
        {
            int actualLen = Encoding.UTF8.GetByteCount(urlEncoded);
            CheckInt($"URL encode length ({actualLen} bytes)", expectedUrlLen, actualLen);
        }
    }
}

// ── URL Encode Comparison Vectors ─────────────
Console.WriteLine();
Console.WriteLine(new string('=', 60));
Console.WriteLine("URL Encode Comparison Vectors");
Console.WriteLine(new string('=', 60));

string ueJson = File.ReadAllText("test-vectors/url-encode-comparison.json", Encoding.UTF8);
using var ueDoc = JsonDocument.Parse(ueJson);
var ueVectors = ueDoc.RootElement.GetProperty("vectors").EnumerateArray().ToList();

for (int i = 0; i < ueVectors.Count; i++)
{
    var v = ueVectors[i];
    string input       = v.GetProperty("input").GetString()!;
    string expectedCMV = v.GetProperty("ecpayUrlEncode").GetString()!;
    string expectedAES = v.GetProperty("aesUrlEncode").GetString()!;

    string cmvResult = EcpayUrlEncode(input);
    string aesResult = AesUrlEncode(input);
    bool cmvOK = cmvResult == expectedCMV;
    bool aesOK = aesResult == expectedAES;
    string status = (cmvOK && aesOK) ? "PASS" : "FAIL";
    if (!cmvOK || !aesOK) failures++;
    Console.WriteLine($"  Vector {i + 1}: {status} | input: {input}");
    if (!cmvOK)
    {
        Console.WriteLine($"    CMV Expected: {expectedCMV}");
        Console.WriteLine($"    CMV Got:      {cmvResult}");
    }
    if (!aesOK)
    {
        Console.WriteLine($"    AES Expected: {expectedAES}");
        Console.WriteLine($"    AES Got:      {aesResult}");
    }
}

// ── Summary ───────────────────────────────────
Console.WriteLine();
Console.WriteLine(new string('=', 60));
int totalCMV = cmvVectors.Count;
int totalAES = aesVectors.Count;
int totalUE  = ueVectors.Count;
Console.WriteLine($"Total: {totalCMV} CMV + {totalAES} AES + {totalUE} URL encode = {totalCMV + totalAES + totalUE} vectors");
if (failures == 0)
{
    Console.WriteLine("ALL PASSED");
}
else
{
    Console.WriteLine($"FAILURES: {failures}");
    Environment.Exit(1);
}
