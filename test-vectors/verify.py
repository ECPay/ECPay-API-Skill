# -*- coding: utf-8 -*-
"""Verify all ECPay test vectors — run: python test-vectors/verify.py
Requires: pip install pycryptodome"""
import hashlib, urllib.parse, json, base64, sys, os

# Windows (cp950) compatible: force UTF-8 stdout so ✓/✗ symbols render correctly
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')

# Ensure running from repo root
for f in ['test-vectors/checkmacvalue.json', 'test-vectors/aes-encryption.json', 'test-vectors/url-encode-comparison.json']:
    if not os.path.exists(f):
        print(f"Error: {f} not found. Please run from ecpay-skill root directory.")
        sys.exit(1)

try:
    from Crypto.Cipher import AES
except ImportError:
    print("Error: pycryptodome required. Run: pip install pycryptodome")
    sys.exit(1)

# ====== ECPay URL Encode (CMV) — matches UrlService::ecpayUrlEncode in PHP SDK ======
# Order: urlencode() → strtolower() → toDotNetUrlEncode()
# Source: scripts/SDK_PHP/src/Services/UrlService.php
def ecpay_url_encode(s):
    encoded = urllib.parse.quote_plus(str(s))
    encoded = encoded.replace('~', '%7E')   # PHP urlencode encodes ~, Python doesn't
    encoded = encoded.lower()               # Step 2: lowercase FIRST
    # Step 3: .NET replacements (now all hex is lowercase, patterns match)
    for old, new in [('%2d','-'),('%5f','_'),('%2e','.'),
                     ('%21','!'),('%2a','*'),('%28','('),('%29',')')]:
        encoded = encoded.replace(old, new)
    return encoded

# ====== AES URL Encode — PHP urlencode only (no .NET, no lowercase) ======
def aes_url_encode(s):
    encoded = urllib.parse.quote_plus(str(s))
    encoded = encoded.replace('~', '%7E')
    return encoded

# ====== CMV calculation ======
def calc_cmv(hash_key, hash_iv, params, method='SHA256'):
    sorted_params = sorted(params.items(), key=lambda x: x[0].lower())
    raw = f'HashKey={hash_key}&' + '&'.join(f'{k}={v}' for k,v in sorted_params) + f'&HashIV={hash_iv}'
    encoded = ecpay_url_encode(raw)
    if method == 'SHA256':
        return hashlib.sha256(encoded.encode('utf-8')).hexdigest().upper()
    else:
        return hashlib.md5(encoded.encode('utf-8')).hexdigest().upper()

# ====== E-Ticket CMV (different formula) ======
def calc_ecticket_cmv(hash_key, hash_iv, json_string):
    raw = hash_key + json_string + hash_iv
    encoded = aes_url_encode(raw).lower()  # E-Ticket: URLEncode → toLowerCase → SHA256 (per official docs)
    return hashlib.sha256(encoded.encode('utf-8')).hexdigest().upper()

# ====== AES encrypt/decrypt ======
def aes_encrypt(plaintext_json, key, iv):
    url_encoded = aes_url_encode(plaintext_json)
    pad_len = 16 - (len(url_encoded.encode('utf-8')) % 16)
    padded = url_encoded.encode('utf-8') + bytes([pad_len] * pad_len)
    cipher = AES.new(key.encode('utf-8')[:16], AES.MODE_CBC, iv.encode('utf-8')[:16])
    return base64.b64encode(cipher.encrypt(padded)).decode('utf-8'), url_encoded

def validate_pkcs7_padding(data):
    pad_len = data[-1]
    if pad_len < 1 or pad_len > 16:
        raise ValueError(f"Invalid PKCS7 padding length: {pad_len}")
    if data[-pad_len:] != bytes([pad_len]) * pad_len:
        raise ValueError(f"Invalid PKCS7 padding bytes")
    return data[:-pad_len]

def aes_decrypt(encrypted_b64, key, iv):
    encrypted = base64.b64decode(encrypted_b64)
    cipher = AES.new(key.encode('utf-8')[:16], AES.MODE_CBC, iv.encode('utf-8')[:16])
    decrypted = cipher.decrypt(encrypted)
    unpadded = validate_pkcs7_padding(decrypted)
    return unpadded.decode('utf-8')

# ====== Test runner ======
failures = 0

def check(label, expected, actual):
    global failures
    if expected == actual:
        print(f"    {label}: PASS ✓")
    else:
        failures += 1
        print(f"    {label}: FAIL ✗")
        print(f"      Expected: {expected}")
        print(f"      Got:      {actual}")

# ====== CMV Vectors ======
print("=" * 60)
print("CheckMacValue Vectors")
print("=" * 60)

with open('test-vectors/checkmacvalue.json', 'r', encoding='utf-8') as f:
    cmv_data = json.load(f)

for i, v in enumerate(cmv_data['vectors'], 1):
    if v.get('formula') == 'ecticket':
        result = calc_ecticket_cmv(v['hashKey'], v['hashIV'], v['plaintext_json'])
    else:
        result = calc_cmv(v['hashKey'], v['hashIV'], v['params'], v['method'])
    status = "PASS ✓" if result == v['expected'] else "FAIL ✗"
    if result != v['expected']: failures += 1
    print(f"  Vector {i}: {status} | {v['name']}")
    if result != v['expected']:
        print(f"    Expected: {v['expected']}")
        print(f"    Got:      {result}")

    # Check wrong_with_percent20
    if 'wrong_with_percent20' in v:
        encoded_correct = ecpay_url_encode(
            f"HashKey={v['hashKey']}&" +
            '&'.join(f'{k}={val}' for k,val in sorted(v['params'].items(), key=lambda x: x[0].lower())) +
            f"&HashIV={v['hashIV']}"
        )
        encoded_wrong = encoded_correct.replace('+', '%20')
        wrong_hash = hashlib.sha256(encoded_wrong.encode('utf-8')).hexdigest().upper()
        check("wrong %20", v['wrong_with_percent20'], wrong_hash)

# ====== AES Vectors ======
print()
print("=" * 60)
print("AES Encryption/Decryption Vectors")
print("=" * 60)

with open('test-vectors/aes-encryption.json', 'r', encoding='utf-8') as f:
    aes_data = json.load(f)

for i, v in enumerate(aes_data['vectors'], 1):
    direction = v.get('direction', 'encrypt')
    if direction == 'decrypt':
        result = aes_decrypt(v['encrypted_base64'], v['hashKey'], v['hashIV'])
        status = "PASS ✓" if result == v['expected_decrypted'] else "FAIL ✗"
        if result != v['expected_decrypted']: failures += 1
        print(f"  Vector {i}: {status} | {v['name']}")
        if result != v['expected_decrypted']:
            print(f"    Expected: {v['expected_decrypted']}")
            print(f"    Got:      {result}")
        # Also verify URL decode → JSON
        url_decoded = urllib.parse.unquote_plus(result)
        check("URL decode → JSON", v['expected_json'], url_decoded)
    elif 'plaintext_json' not in v:
        # 說明性向量（無 expected_base64），僅印出名稱，跳過驗證
        print(f"  Vector {i}: SKIP (explanatory) | {v['name']}")
    else:
        b64, url_enc = aes_encrypt(v['plaintext_json'], v['hashKey'], v['hashIV'])
        status = "PASS ✓" if b64 == v['expected_base64'] else "FAIL ✗"
        if b64 != v['expected_base64']: failures += 1
        print(f"  Vector {i}: {status} | {v['name']}")
        if b64 != v['expected_base64']:
            print(f"    Expected: {v['expected_base64']}")
            print(f"    Got:      {b64}")
        if 'expected_url_encoded' in v:
            check("URL encode", v['expected_url_encoded'], url_enc)
        if 'expected_url_encoded_length' in v:
            actual_len = len(url_enc.encode('utf-8'))
            check(f"URL encode length ({actual_len} bytes)", v['expected_url_encoded_length'], actual_len)

# ====== URL Encode Comparison Vectors ======
print()
print("=" * 60)
print("URL Encode Comparison Vectors")
print("=" * 60)

with open('test-vectors/url-encode-comparison.json', 'r', encoding='utf-8') as f:
    ue_data = json.load(f)

for i, v in enumerate(ue_data['vectors'], 1):
    cmv_result = ecpay_url_encode(v['input'])
    aes_result = aes_url_encode(v['input'])
    cmv_ok = cmv_result == v['ecpayUrlEncode']
    aes_ok = aes_result == v['aesUrlEncode']
    status = "PASS ✓" if (cmv_ok and aes_ok) else "FAIL ✗"
    if not (cmv_ok and aes_ok): failures += 1
    print(f"  Vector {i}: {status} | input: {v['input']}")
    if not cmv_ok:
        print(f"    CMV Expected: {v['ecpayUrlEncode']}")
        print(f"    CMV Got:      {cmv_result}")
    if not aes_ok:
        print(f"    AES Expected: {v['aesUrlEncode']}")
        print(f"    AES Got:      {aes_result}")

# ====== Summary ======
print()
print("=" * 60)
total_cmv = len(cmv_data['vectors'])
total_aes = len(aes_data['vectors'])
total_ue = len(ue_data['vectors'])
print(f"Total: {total_cmv} CMV + {total_aes} AES + {total_ue} URL encode = {total_cmv+total_aes+total_ue} vectors")
if failures == 0:
    print("ALL PASSED ✓")
else:
    print(f"FAILURES: {failures}")
    sys.exit(1)

