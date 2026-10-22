// Nostek-NMSOLT GenieACS container healthcheck: the service answers HTTP on its
// own port (any status below 500). Usage: node nos-genieacs-health.js <port>
'use strict';
const http = require('http');
const port = Number(process.argv[2]);
if (!Number.isInteger(port) || port < 1 || port > 65535) {
  process.exit(2);
}
const req = http.get({ host: '127.0.0.1', port: port, path: '/', timeout: 4000 }, (res) => {
  res.resume();
  process.exit(res.statusCode < 500 ? 0 : 1);
});
req.on('timeout', () => { req.destroy(); process.exit(1); });
req.on('error', () => process.exit(1));
