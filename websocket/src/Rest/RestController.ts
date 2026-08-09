import { FastifyRequest, FastifyReply, FastifyInstance } from 'fastify';
import { ConnectionRegistry } from '../ConnectionRegistry';
import { SessionIdProvider } from '../SessionIdProvider';

export class RestController {
    private readonly sessionIdProvider = new SessionIdProvider();
    private readonly connectionRegistry: ConnectionRegistry;

    constructor (connectionRegistry: ConnectionRegistry, instance: FastifyInstance) {
        this.connectionRegistry = connectionRegistry;
        instance.get("/stats", this.stats.bind(this));
        instance.get("/users/:id/is-online", this.userIsConnected.bind(this));
        // :ids: You can post to multiple user ids separating them with commas (,).
        instance.post("/users/:ids/:channel/:method", this.send.bind(this));
    }

    async stats (request: FastifyRequest, reply: FastifyReply): Promise<any> {
        return reply.send({
            connections: this.connectionRegistry.numConnections,
            registrations: this.connectionRegistry.numRegistrations,
            sessions: this.connectionRegistry.numRegisteredSessions
        });
    }

    async userIsConnected (request: FastifyRequest, reply: FastifyReply): Promise<any> {
        const params = request.params as { id: string };
        const userId = Number(params.id);
        const sessionIds = await this.sessionIdProvider.fetchSessionIdsForUser(userId);
        const connections = this.connectionRegistry.getConnectionsForSessions(sessionIds);

        for (const connection of connections) {
            if (connection.clientIsHidden) {
                continue;
            }
            return reply.send(true);
        }

        return reply.send(false);
    }


    async send (request: FastifyRequest, reply: FastifyReply): Promise<any> {
        const params = request.params as { ids: string; channel: string; method: string };
        const userIds: number[] = params.ids.split(',').map(Number);
        const sessionIds = await this.sessionIdProvider.fetchSessionIdsForUsers(userIds);
        const connections = this.connectionRegistry.getConnectionsForSessions(sessionIds);

        for (const connection of connections) {
            connection.send(params.channel, { m: params.method, o: request.body });
        }

        return reply.send();
    }
}