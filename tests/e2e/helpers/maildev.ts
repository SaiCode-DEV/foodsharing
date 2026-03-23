import { expect } from "@playwright/test";

class MailResult {
  constructor(
    private mail: any,
    private maildev: Maildev,
  ) {}

  /**
   * Find and extract a link from the mail that contains the given pattern.
   * Searches in both text and HTML content.
   * @param pattern The pattern to search for in the URL (e.g., 'password-reset')
   * @returns The extracted and cleaned URL, or null if not found
   */
  findLink(pattern: string): string | null {
    // Try to extract from text first
    if (this.mail.text) {
      const link = this.extractFromText(this.mail.text, pattern);
      if (link) return link;
    }

    // Try to extract from html
    if (this.mail.html) {
      const link = this.extractFromHtml(this.mail.html, pattern);
      if (link) return link;
    }

    return null;
  }

  private extractFromText(text: string, pattern: string): string | null {
    // Match URLs containing the pattern (exclude brackets)
    const regex = new RegExp(
      `https?://[^\\s<>'"\\[\\]]+${pattern}[^\\s<>'"\\[\\]]*`,
      "gi",
    );
    const matches = text.match(regex);
    return matches?.[0] ? this.maildev.replaceUrl(matches[0]) : null;
  }

  private extractFromHtml(html: string, pattern: string): string | null {
    // Extract all URLs from HTML (exclude brackets)
    const urls = Array.from(html.matchAll(/https?:\/\/[^"'\s<[\]]+/g)).map(
      (match) => {
        const url = match[0].replace(/&amp;/g, "&");
        return url;
      },
    );

    // Find first URL containing the pattern
    const found = urls.find((url) => url.includes(pattern));
    return found ? this.maildev.replaceUrl(found) : null;
  }
}

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

  /**
   * Wait for a mail to arrive and return it wrapped in a MailResult.
   * @param subject Optional subject string to match the mail subject.
   * @param toAddress Optional recipient email address to filter by.
   * @param timeout Number of milliseconds to wait (default 10000 ms). Checks every second.
   * @returns A MailResult object with helper methods, or null if not found within timeout
   */
  async waitForMail(
    subject?: string,
    toAddress?: string,
    timeout: number = 10000,
  ): Promise<MailResult | null> {
    const checkInterval = 1000; // Check every second
    const deadline = Date.now() + Math.max(0, timeout);

    do {
      const mails = await this.getMails();

      const found = mails.find((mail: any) => {
        // Filter by recipient if provided
        if (toAddress) {
          const recipients = Array.isArray(mail.to) ? mail.to : [];
          const matchedRecipient = recipients.some(
            (r: any) =>
              (r.address || "").toLowerCase() === toAddress.toLowerCase(),
          );
          if (!matchedRecipient) return false;
        }

        // Filter by subject if provided
        if (subject) {
          return mail.subject && mail.subject.includes(subject);
        }

        // If no filters given, return first mail
        return true;
      });

      if (found) return new MailResult(found, this);

      const remainingTime = deadline - Date.now();
      if (remainingTime <= 0) break;

      await new Promise((resolve) =>
        setTimeout(resolve, Math.min(checkInterval, remainingTime)),
      );
    } while (true);

    return null;
  }

  /**
   * Extracts a matching http(s) link from mail HTML and decodes HTML entities.
   * If pattern is given (string), returns first URL containing pattern.
   * Otherwise returns the first URL found. Returns null if none found.
   */
  async extractLinkFromHtml(
    html: string,
    pattern?: string,
  ): Promise<string | null> {
    if (!html) return null;
    const urls = Array.from(html.matchAll(/https?:\/\/[^"'\s<]+/g)).map((m) => {
      const url = m[0].replace(/&amp;/g, "&");
      if (pattern) {
        const match = url.match(new RegExp(`(${pattern}\\?[^"'\\s<]*)`));
        return this.replaceUrl(match ? match[0] : url);
      }
      return this.replaceUrl(url);
    });
    if (!pattern) return urls[0] || null;
    return this.replaceUrl(urls.find((url) => url.includes(pattern)) || null);
  }
}

export const maildev = new Maildev();
