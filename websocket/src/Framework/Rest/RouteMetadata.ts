type HttpMethod = 'get' | 'post' | 'delete' | 'put' | 'options' | 'patch';

export interface RouteMetadata {
    readonly requestMethod: HttpMethod
    readonly path: string
    readonly controllerMethodName: string
}