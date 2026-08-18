import { FastifyServerFacade } from './Rest/FastifyServerFacade';
import { ConnectionRegistry } from './ConnectionRegistry';
import { SocketIOServerFacade } from './WebSocket/SocketIOServerFacade';

async function start(): Promise<void> {
    const socketRegistry = new ConnectionRegistry();

    const restServer = new FastifyServerFacade(socketRegistry);
    await restServer.listen(1338);

    const socketServer = new SocketIOServerFacade(socketRegistry);
    socketServer.listen(1337);
}

start().catch(console.error);
