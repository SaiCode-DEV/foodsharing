import { FastifyRequest, FastifyReply, FastifyInstance } from 'fastify';
import { ConnectionRegistry } from '../ConnectionRegistry';
import { SessionIdProvider } from '../SessionIdProvider';

export class RestController {
    private readonly sessionIdProvider = new SessionIdProvider();
    private readonly connectionRegistry: ConnectionRegistry;

    constructor(connectionRegistry: ConnectionRegistry, instance: FastifyInstance) {
        this.connectionRegistry = connectionRegistry;
        instance.get('/stats', this.stats.bind(this));
        instance.get('/users/:id/is-online', this.userIsConnected.bind(this));
        instance.post('/users/:channel/:method', this.send.bind(this));
    }

    async stats(request: FastifyRequest, reply: FastifyReply): Promise<any> {
        return reply.send({
            connections: this.connectionRegistry.numConnections,
            registrations: this.connectionRegistry.numRegistrations,
            sessions: this.connectionRegistry.numRegisteredSessions,
        });
    }

    async userIsConnected(request: FastifyRequest, reply: FastifyReply): Promise<any> {
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

    async send(request: FastifyRequest, reply: FastifyReply): Promise<any> {
        const params = request.params as { channel: string; method: string };
        const { fsIds, content } = request.body as { fsIds: number[]; content: unknown };
        if (!fsIds || !Array.isArray(fsIds) || fsIds.length === 0) {
            return reply.status(400).send({ error: 'Missing or invalid user IDs' });
        }
        const sessionIds = await this.sessionIdProvider.fetchSessionIdsForUsers(fsIds);
        const connections = this.connectionRegistry.getConnectionsForSessions(sessionIds);
        for (const connection of connections) {
            connection.send(params.channel, { m: params.method, o: content });
        }

        return reply.send();
    }
}
