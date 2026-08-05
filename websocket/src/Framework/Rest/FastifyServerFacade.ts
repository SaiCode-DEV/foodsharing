import 'reflect-metadata';
import Fastify, { FastifyInstance, FastifyRequest, FastifyReply } from 'fastify';
import formBody from '@fastify/formbody';
import { RouteMetadata } from './RouteMetadata';
import { ServerFacade } from '../ServerFacade';

export class FastifyServerFacade implements ServerFacade {
    private readonly server: FastifyInstance;

    constructor () {
        this.server = Fastify({
            bodyLimit: 50000,
             // Length for URL parameters, e.g. for long query strings due to lots of foodsaver-IDs. Without this, the limmit is 100 characters
             // and longer query strings will result in 404 errors, because no route matched
            maxParamLength: 50000,
        });
         
        // Register plugins
        void this.server.register(formBody);
    }

    async listen (port: number): Promise<void> {
        // Wait for all plugins to be ready
        await this.server.ready();
        
        try {
            await this.server.listen({ port, host: '0.0.0.0' });
            console.log(`REST server listening on port ${port}`);
        } catch (err) {
            console.error(err);
            process.exit(1);
        }
    }

    /**
     * Reads out the route configuration decorators from the given controller and makes the routes accessible
     * over the server. Supports all decorators that provide the 'routes' metadata key with instances of RouteMetadata.
     *
     * You can find supported decorators in rest-decorators.ts
     */
    loadControllerDecorators (controller: Record<string, any> & any): void {
        const routes: RouteMetadata[] = Reflect.getMetadata('routes', controller.constructor);
        for (const route of routes) {
            const methodName: string = route.controllerMethodName;

            if (typeof controller[methodName] !== 'function') {
                throw new Error(`Method ${methodName} is not defined on the given controller.`);
            }
            
            const handler = async (request: FastifyRequest, reply: FastifyReply) => {
                try {
                    const result = await controller[methodName](request, reply);
                    
                    if (result instanceof Promise) {
                        return await result;
                    }
                    
                    return result;
                } catch (error) {
                    reply.code(500).send(error);
                }
            };

            // Use type assertion to work around TypeScript issues
            (this.server as any).route({
                method: route.requestMethod.toUpperCase(),
                url: route.path,
                handler
            });
        }
    }
}