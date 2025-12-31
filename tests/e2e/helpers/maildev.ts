import { expect } from "@playwright/test";

export class Maildev {
  private url: string;

  constructor(url?: string) {
    const fsEnv = process.env.FS_ENV;
    if (url) {
      this.url = url;
    } else if (process.env.CI) {
      // Determine URL based on environment
      // In CI environment, maildev is available as service alias
      this.url = "http://maildev:1080";
    } else if (fsEnv === "test") {
      this.url = "http://localhost:28084";
    } else {
      // Default to dev environment
      this.url = "http://localhost:18084";
    }
  }

  replaceUrl(url: string): string {
    return url.replace("http://lmr.local", "");
  }

  async getMails(): Promise<any[]> {
    const response = await fetch(this.url + "/email");
    return response.json();
  }

  async deleteMail(id: string): Promise<void> {
    await fetch(this.url + "/email/" + id, { method: "DELETE" });
  }

  async deleteAllMails(): Promise<void> {
    await fetch(this.url + "/email/all", { method: "DELETE" });
  }

  async expectNumMails(num: number, timeout: number = 5): Promise<void> {
    if (timeout) {
      while (timeout > 0) {
        const mails = await this.getMails();
        if (mails.length === num) {
          return;
        }
        timeout--;
        await new Promise((resolve) => setTimeout(resolve, 1000));
      }
    }
    const mails = await this.getMails();
    expect(mails).toHaveLength(num);
  }
}

export const maildev = new Maildev();
