import { createServer } from 'http';
import { Server } from 'socket.io';
import { ConnectionRegistry } from '../ConnectionRegistry';
import { SocketController } from './SocketController';

export class SocketIOServerFacade {
    private readonly server = createServer();
    private readonly socketIo = new Server(this.server);
    private readonly registry: ConnectionRegistry;

    constructor(registry: ConnectionRegistry) {
        this.registry = registry;
        new SocketController(this.registry, this.socketIo);
    }

    listen(port: number): void {
        this.server.listen(port);
    }
}
