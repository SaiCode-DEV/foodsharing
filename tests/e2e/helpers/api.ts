import { APIRequestContext } from "@playwright/test";

export class Api {
  private request: APIRequestContext;

  constructor(request: APIRequestContext) {
    this.request = request;
  }

  async seeResponseIsHtml(response: any): Promise<void> {
    const text = await response.text();
    if (!text.match(/<!doctype html>/im)) {
      throw new Error("Response is not HTML");
    }
  }

  async seeRegExp(pattern: RegExp, response: any): Promise<void> {
    const text = await response.text();
    if (!text.match(pattern)) {
      throw new Error(`Pattern ${pattern} not found in response`);
    }
  }

  async dontSeeRegExp(pattern: RegExp, response: any): Promise<void> {
    const text = await response.text();
    if (text.match(pattern)) {
      throw new Error(`Pattern ${pattern} should not be in response`);
    }
  }

  async login(email: string, pass: string = "password"): Promise<any> {
    return await this.request.post("api/user/login", {
      form: {
        email,
        password: pass,
      },
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
    });
  }
}

export const api = new Api(null as any); // Will be initialized with proper request context
