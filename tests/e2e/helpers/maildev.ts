import { test, expect } from '@playwright/test';

export class Maildev {
  private url: string;

  constructor(url: string = 'http://maildev:1080') {
    this.url = url;
  }

  async getMails(): Promise<any[]> {
    const response = await fetch(this.url + '/email');
    return response.json();
  }

  async deleteAllMails(): Promise<void> {
    await fetch(this.url + '/email/all', { method: 'DELETE' });
  }

  async expectNumMails(num: number, timeout: number = 5): Promise<void> {
    if (timeout) {
      while (timeout > 0) {
        const mails = await this.getMails();
        if (mails.length === num) {
          return;
        }
        timeout--;
        await new Promise(resolve => setTimeout(resolve, 1000));
      }
    }
    const mails = await this.getMails();
    expect(mails).toHaveLength(num);
  }
}

export const maildev = new Maildev();
