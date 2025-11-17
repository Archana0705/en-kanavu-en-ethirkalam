// encrypt_decrypt.js

function encryptData(data) {
    const secretKey = "xmcK|fbngp@!71L$";
    const key = CryptoJS.enc.Hex.parse(CryptoJS.SHA256(secretKey).toString());
    const iv = CryptoJS.lib.WordArray.random(16);

    const encrypted = CryptoJS.AES.encrypt(JSON.stringify(data), key, {
        iv: iv,
        mode: CryptoJS.mode.CBC,
        padding: CryptoJS.pad.Pkcs7,
    });

    const combinedData = iv.concat(encrypted.ciphertext);
    return CryptoJS.enc.Base64.stringify(combinedData);
}

function decryptData(encryptedData) {
    const secretKey = "xmcK|fbngp@!71L$";
    const key = CryptoJS.enc.Hex.parse(CryptoJS.SHA256(secretKey).toString());
    const decodedData = CryptoJS.enc.Base64.parse(encryptedData).toString(CryptoJS.enc.Hex);

    const ivHex = decodedData.slice(0, 32);
    const cipherHex = decodedData.slice(32);
    const iv = CryptoJS.enc.Hex.parse(ivHex);
    const ciphertext = CryptoJS.enc.Hex.parse(cipherHex);

    const decrypted = CryptoJS.AES.decrypt(
        { ciphertext: ciphertext },
        key,
        {
            iv: iv,
            mode: CryptoJS.mode.CBC,
            padding: CryptoJS.pad.Pkcs7,
        }
    );

    return JSON.parse(decrypted.toString(CryptoJS.enc.Utf8));
}

// Make globally available
window.encryptData = encryptData;
window.decryptData = decryptData;
