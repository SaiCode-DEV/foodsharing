import * as util from 'util';
import * as fs from 'fs';
import { TedisPool } from 'tedis';
import path = require('path');

export class SessionIdProvider {
    private readonly redisClientPool = new TedisPool({
        host: process.env.REDIS_HOST ?? '127.0.0.1',
        port: Number(process.env.REDIS_PORT) || 6379
    });

    /**
     * This script can be uploaded to Redis to retrieve all current session ids of a user.
     */
    private readonly sessionIdsScriptFilename = path.join(__dirname, '../', 'session-ids.lua');
    /**
     * Once uploaded to Redis, the script is identified by an SHA hash. This can be used to tell Redis to execute the
     * script.
     */
    private sessionIdsScriptSHA = "";

    async fetchSessionIdsForUser (userId: number): Promise<string[]> {
        const sha = await this.getSessionIdsScriptSHA();
        const redisClient = await this.redisClientPool.getTedis()
        try {
            return await redisClient.command('EVALSHA', sha, 0, userId);
        } catch (err) {
            if (typeof err === "object" && err !== null && 'code' in err && err.code !== 'NOSCRIPT') {
                throw err;
            }
            await this.uploadSessionIdsScriptToRedis();
            return await redisClient.command('EVALSHA', sha, 0, userId);
        } finally {
            this.redisClientPool.putTedis(redisClient)
        }
    }

    async fetchSessionIdsForUsers (userIds: number[]): Promise<string[]> {
        const sessionIds: string[] = [];
        for (const userId of userIds) {
            const sessionIdsForUser = await this.fetchSessionIdsForUser(userId);
            sessionIds.push(...sessionIdsForUser);
        }
        return sessionIds;
    }

    private async getSessionIdsScriptSHA (): Promise<string> {
        if (!this.sessionIdsScriptSHA) {
            await this.uploadSessionIdsScriptToRedis();
        }

        return this.sessionIdsScriptSHA;
    }

    private async uploadSessionIdsScriptToRedis (): Promise<void> {
        const contents = await util.promisify(fs.readFile)(this.sessionIdsScriptFilename, 'utf8');
        const redisClient = await this.redisClientPool.getTedis()
        this.sessionIdsScriptSHA = await redisClient.command('SCRIPT', 'LOAD', contents);
        this.redisClientPool.putTedis(redisClient)
    }
}
