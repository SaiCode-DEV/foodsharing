export const Urls = {
  groupListUrl(): string {
    return "/groups";
  },

  groupEditUrl(groupId: number): string {
    return `/region?sub=edit&bid=${groupId}`;
  },

  forumUrl(id: number): string {
    return `/region?bid=${id}&sub=forum`;
  },
};
