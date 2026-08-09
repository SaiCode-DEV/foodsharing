import Fastify, { FastifyInstance, FastifyRequest, FastifyReply } from 'fastify';
import { ConnectionRegistry } from '../ConnectionRegistry';
import { RestController } from './RestController';

export class FastifyServerFacade {
    private readonly server: FastifyInstance;
    private readonly registry: ConnectionRegistry;

    constructor (registry: ConnectionRegistry) {
        this.registry = registry;
        this.server = Fastify({
            bodyLimit: 50000,
             // Length for URL parameters, e.g. for long query strings due to lots of foodsaver-IDs. Without this, the limmit is 100 characters
             // and longer query strings will result in 404 errors, because no route matched
            maxParamLength: 50000,
        });
         
        new RestController(this.registry, this.server);
    }

    async listen (port: number): Promise<void> {
        try {
            await this.server.listen({ port, host: '0.0.0.0' });
            console.log(`REST server listening on port ${port}`);
        } catch (err) {
            console.error(err);
            process.exit(1);
        }
    }
}