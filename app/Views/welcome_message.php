<h1>Hello nga</h1>
<script>
/**
 * Decodes the payload section of a non-encrypted JWT.
 * Note: This only works in modern browser environments (not older Node.js versions).
 * @param {string} token The full JWT string.
 * @returns {object} The parsed JWT payload object.
 */
function decodeJwtPayload(token) {
  // 1. Get the payload (second part of the token)
  const base64Url = token.split('.')[1];
  
  // 2. Convert from Base64Url to standard Base64
  //    - Replaces '-' with '+' and '_' with '/'
  const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
  
  // 3. Use atob to decode the Base64 string into a string of bytes (Latin-1)
  const jsonString = atob(base64);

  // 4. Use JSON.parse to turn the JSON string into a JavaScript object
  //    Note: This simplified approach relies on modern browser atob/JSON handling
  return JSON.parse(jsonString);
}


// --- Example Usage ---
const sampleToken = "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpYXQiOjE3NjQ1NjYxMjYsImV4cCI6MTc2NDY1MjUyNiwiZGF0YSI6eyJpZCI6IjIiLCJ1c2VybmFtZSI6IkZ3QWRtaW4iLCJlbWFpbCI6ImZvcnR1bmV3aGVlbEBleGFtcGxlLmNvbSIsInR5cGUiOiJjbGllbnQifX0.CJfxJuQLhCBw3xyPLhe9Q0-wwJa9vu6wCCzUMbzxroY";

const payload = decodeJwtPayload(sampleToken);

console.log("Decoded JWT Payload:", payload);
console.log("Username:", payload.data.username); 

/*
Decoded JWT Payload: {
  iat: 1764557659,
  exp: 1764644059,
  data: {
    id: '2',
    username: 'FwAdmin',
    email: 'fortunewheel@example.com'
  }
}
*/

</script>