/**
 * Facade to connect server logic that is organized in decorator-based controllers to a callback-based server
 * implementation.
 */
export interface ServerFacade {
    /**
     * Makes the server behind the facade listen to the specified port.
     * Can be synchronous or asynchronous.
     */
    listen: (port: number) => void | Promise<void>
    /**
     * Loads all supported decorators/annotations from a controller class.
     */
    loadControllerDecorators: (controller: Record<string, any> & any) => void
}