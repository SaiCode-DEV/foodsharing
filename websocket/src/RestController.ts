import { FastifyRequest, FastifyReply } from 'fastify';
import { Get, Post } from './Framework/Rest/rest-decorators';
import { ConnectionRegistry } from './ConnectionRegistry';
import { SessionIdProvider } from './SessionIdProvider';

export class RestController {
    private readonly sessionIdProvider = new SessionIdProvider();
    private readonly connectionRegistry: ConnectionRegistry;

    constructor (connectionRegistry: ConnectionRegistry) {
        this.connectionRegistry = connectionRegistry;
    }

    @Get('/stats')
    async stats (request: FastifyRequest, reply: FastifyReply): Promise<any> {
        return reply.send({
            connections: this.connectionRegistry.numConnections,
            registrations: this.connectionRegistry.numRegistrations,
            sessions: this.connectionRegistry.numRegisteredSessions
        });
    }

    @Get('/users/:id/is-online')
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

    /**
     * :ids: You can post to multiple user ids separating them with commas (,).
     */
    @Post('/users/:ids/:channel/:method')
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