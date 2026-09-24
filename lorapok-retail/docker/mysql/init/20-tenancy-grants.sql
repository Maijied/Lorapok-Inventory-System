-- stancl/tenancy provisions one database per shop, which needs CREATE DATABASE
-- and GRANT. The application user (`sail`) deliberately does NOT get these — it
-- only ever touches the central DB and already-provisioned tenant DBs.
-- Provisioning runs as root via TENANCY_DB_USERNAME (see .env).
CREATE DATABASE IF NOT EXISTS `lorapok_central`;
GRANT ALL PRIVILEGES ON `lorapok_central`.* TO 'sail'@'%';
-- Tenant databases are named `tenant_<uuid>`; grant the app user access to the
-- whole namespace so it can connect once a tenant DB has been created.
GRANT ALL PRIVILEGES ON `tenant\_%`.* TO 'sail'@'%';
FLUSH PRIVILEGES;
