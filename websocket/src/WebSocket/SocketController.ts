import { Server, Socket } from 'socket.io';
import { Connection } from '../Connection';
import { ConnectionRegistry } from '../ConnectionRegistry';
import { parse as parseCookie } from 'cookie';

export class SocketController {
    private readonly connectionRegistry: ConnectionRegistry;

    constructor(connectionRegistry: ConnectionRegistry, socket: Server) {
        this.connectionRegistry = connectionRegistry;
        socket.on('connection', (socket) => {
            this.onConnect(socket);
            socket.on('disconnect', () => this.onDisconnect(socket));
            socket.on('visibilitychange', (hidden: boolean) => this.onClientVisibilityChange(socket, hidden));
        });
    }

    onConnect(socket: Socket): void {
        this.connectionRegistry.numConnections++;

        const sessionId = this.readSessionId(socket);
        this.connectionRegistry.register(sessionId, new Connection(socket));
    }

    onDisconnect(socket: Socket): void {
        this.connectionRegistry.numConnections--;
        try {
            const sessionId = this.readSessionId(socket);
            this.connectionRegistry.removeRegistration(sessionId, socket.id);
        } catch {
            // Socket had no valid session cookie and was never registered — nothing to clean up
        }
    }

    onClientVisibilityChange(socket: Socket, hidden: boolean): void {
        const sessionId = this.readSessionId(socket);
        const connection = this.connectionRegistry.getConnection(sessionId, socket.id);
        if (!connection) {
            return;
        }
        connection.clientIsHidden = hidden;
    }

    private readSessionId(socket: Socket): string {
        const cookieVal = socket.request.headers.cookie;
        if (!cookieVal) {
            throw new Error('not authorized');
        }
        const cookie = parseCookie(cookieVal);
        const sessionId = cookie.FS_SESSID || cookie.sessionid;
        if (!sessionId) {
            throw new Error('no session ID in cookie');
        }
        return sessionId;
    }
}
