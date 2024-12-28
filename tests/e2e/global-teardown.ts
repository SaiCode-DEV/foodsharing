import { Database } from './helpers/database';

async function globalTeardown() {
  await Database.cleanup();
}

export default globalTeardown;
