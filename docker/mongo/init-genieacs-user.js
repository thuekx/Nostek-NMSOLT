// Nostek-NMSOLT with-acs profile: first-start MongoDB initialisation.
// Runs once (empty data directory only) from /docker-entrypoint-initdb.d as the
// root account. Creates the least-privilege account GenieACS uses. Values come
// from the container environment; nothing is printed.
const user = process.env.MONGO_ACS_APP_USERNAME || 'genieacs';
const pwd = process.env.MONGO_ACS_APP_PASSWORD;
if (!pwd) {
  throw new Error('MONGO_ACS_APP_PASSWORD is not set');
}
db.getSiblingDB('genieacs').createUser({
  user: user,
  pwd: pwd,
  roles: [{ role: 'readWrite', db: 'genieacs' }],
});
