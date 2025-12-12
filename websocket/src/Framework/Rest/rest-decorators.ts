/**
 * Rest Decorators
 *
 * The decorators in this file can be read by the RestServerFacade.
 *
 * These decorators can be used to declare routes to controller methods. Use the decorator matching your desired
 * request method and pass the path as a string.
 */

import { RouteMetadata } from './RouteMetadata';
import { pushMetadata } from '../push-to-metadata-array';

export const Get = (path: string): any => {
    return (targetObject: Record<string, any>, controllerMethodName: string) => {
        const route: RouteMetadata = {
            requestMethod: 'get',
            path,
            controllerMethodName
        };
        pushMetadata('routes', route, targetObject);
    };
};

export const Post = (path: string): any => {
    return (targetObject: Record<string, any>, controllerMethodName: string) => {
        const route: RouteMetadata = {
            requestMethod: 'post',
            path,
            controllerMethodName
        };
        pushMetadata('routes', route, targetObject);
    };
};

export const Put = (path: string): any => {
    return (targetObject: Record<string, any>, controllerMethodName: string) => {
        const route: RouteMetadata = {
            requestMethod: 'put',
            path,
            controllerMethodName
        };
        pushMetadata('routes', route, targetObject);
    };
};

export const Delete = (path: string): any => {
    return (targetObject: Record<string, any>, controllerMethodName: string) => {
        const route: RouteMetadata = {
            requestMethod: 'delete',
            path,
            controllerMethodName
        };
        pushMetadata('routes', route, targetObject);
    };
};

export const Patch = (path: string): any => {
    return (targetObject: Record<string, any>, controllerMethodName: string) => {
        const route: RouteMetadata = {
            requestMethod: 'patch',
            path,
            controllerMethodName
        };
        pushMetadata('routes', route, targetObject);
    };
};

export const Options = (path: string): any => {
    return (targetObject: Record<string, any>, controllerMethodName: string) => {
        const route: RouteMetadata = {
            requestMethod: 'options',
            path,
            controllerMethodName
        };
        pushMetadata('routes', route, targetObject);
    };
};