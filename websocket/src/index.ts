import { RestController } from './RestController';
import { FastifyServerFacade } from './Framework/Rest/FastifyServerFacade';
import { ConnectionRegistry } from './ConnectionRegistry';
import { SocketIOServerFacade } from './Framework/WebSocket/SocketIOServerFacade';
import { SocketController } from './SocketController';

async function start (): Promise<void> {
    const socketRegistry = new ConnectionRegistry();

    const restServer = new FastifyServerFacade();
    restServer.loadControllerDecorators(new RestController(socketRegistry));
    await restServer.listen(1338);

    const socketServer = new SocketIOServerFacade();
    socketServer.loadControllerDecorators(new SocketController(socketRegistry));
    socketServer.listen(1337);
}

start().catch(console.error);