import { fakerDE as faker } from "@faker-js/faker";
import argon2 from "argon2";
import { DateTime } from "luxon";
import path from "path";
import { Database } from "./database";
import fs from "fs";
import { v4 as uuidv4 } from "uuid";
import { mkdirp } from "mkdirp";

// Import enums from separate files
import Role from "./constants/Foodsaver/Role";
import VotingScope from "./constants/Voting/VotingScope";
import VotingType from "./constants/Voting/VotingType";
import RegionIDs from "./constants/Region/RegionIDs";
import UnitType from "./constants/Region/UnitType";
import QuizID from "./constants/Quiz/QuizID";

class UploadedFile {
  constructor(
    public filePath: string,
    public fileSize: number,
    public hashedBody: string,
    public mimeType: string,
    public uploaderId: number,
    public id: number | null = null,
    public uuid: string | null = null,
  ) {}
}

class Foodsharing {
  async clear(): Promise<void> {
    const regionsToKeep = [
      RegionIDs.ROOT,
      258, // Orgateam Archive
      RegionIDs.QUIZ_AND_REGISTRATION_WORK_GROUP,
      RegionIDs.GLOBAL_WORKING_GROUPS,
      RegionIDs.TEAM_BOARD_MEMBER,
      RegionIDs.TEAM_ALUMNI_MEMBER,
      RegionIDs.TEAM_ADMINISTRATION_MEMBER,
    ].join(",");

    const tablesToSkip = [
      "fs_bezirk",
      "fs_content",
      "fs_fetchweight",
      "fs_bezirk_closure",
      "phinxlog",
    ]
      .map((t) => `'${t}'`)
      .join(",");

    const conn = await Database.connect();

    // Get all tables except skipped ones
    const [tables] = await conn.execute(`
      SELECT table_name
      FROM information_schema.tables 
      WHERE table_type = 'BASE TABLE'
        AND table_schema = DATABASE()
        AND table_name NOT IN (${tablesToSkip})
    `);

    // Clear each table
    for (const table of tables as any[]) {
      await conn.execute(`DELETE FROM ${table.TABLE_NAME}`);
    }

    // Clear regions except protected ones
    await conn.execute(`
      DELETE FROM fs_bezirk WHERE id NOT IN(${regionsToKeep}) and type = 7;
      DELETE FROM fs_bezirk WHERE id NOT IN(${regionsToKeep}) and id in (
        SELECT bez.id FROM fs_bezirk bez
        left outer join fs_bezirk par on bez.id = par.parent_id
        where par.parent_id is null
      );
      DELETE FROM fs_bezirk WHERE id NOT IN(${regionsToKeep});
    `);
  }

  /**
   * Create a new foodsharer with random data
   * @param pass Password to use for the foodsharer
   * @param extraParams Additional parameters to add to the foodsharer
   * @returns The created foodsharer
   */
  async createFoodsharer(
    pass: string = null,
    extraParams: any = {},
  ): Promise<any> {
    if (!pass) {
      pass = "password";
    }

    let pictureUrl = null;
    const gender =
      Math.random() > 0.1
        ? Math.floor(Math.random() * 2)
        : Math.floor(Math.random() * 2) + 2;

    // Handle profile picture upload if specified
    if (extraParams.image && (gender == 0 || gender == 1)) {
      const genderDir = ["men", "women"][gender];
      const imgNum = Math.floor(Math.random() * 100);
      const imgPath = path.join(
        "./img/seed-data/profile/",
        genderDir,
        `${imgNum}.jpg`,
      );

      const stats = await fs.promises.stat(imgPath);
      const hash = await this.hashFile("sha256", imgPath);

      const profilePicture = new UploadedFile(
        imgPath,
        stats.size,
        hash,
        "image/jpg",
        1,
      );

      const uuid = await this.uploadFile(profilePicture);
      pictureUrl = `/api/uploads/${uuid}`;
    }

    const firstName = faker.person.firstName();
    const lastName = faker.person.lastName();

    // Ensure the quizes actually exist. This avoids spurious 404 errors
    // during e2e tests
    if (!extraParams.skip_quiz_creation) {
      await this.createQuizes();
    }
    // remove the param so it doesn't interfere with addToDatabase (if set)
    if (extraParams.skip_quiz_creation !== undefined) {
      delete extraParams.skip_quiz_creation;
    }

    const params = {
      email: faker.internet.email({ firstName, lastName }),
      bezirk_id: 0,
      name: firstName,
      nachname: lastName,
      deleted_at: null,
      verified: 0,
      rolle: 0,
      plz: faker.location.zipCode(),
      stadt: faker.location.city(),
      lat: faker.location.latitude({ min: 46, max: 55 }),
      lon: faker.location.longitude({ min: 4, max: 16 }),
      anmeldedatum: faker.date.past({ years: 5 }),
      geb_datum: faker.date.birthdate({ min: 18, max: 80, mode: "age" }),
      last_login: faker.date.recent({ days: 365 }),
      anschrift: faker.location.street(),
      handy: faker.phone.number({ style: "international" }),
      active: 1,
      token: faker.string.uuid(),
      photo: pictureUrl,
      geschlecht: gender,
      ...extraParams,
    };

    // Convert password to hash using ARGON2I
    params.password = await this.hashPassword(pass);

    // Format dates
    params.geb_datum = this.toDateTime(params.geb_datum);
    params.last_login = this.toDateTime(params.last_login);
    params.anmeldedatum = this.toDateTime(params.anmeldedatum);

    // Insert into database
    params.id = await Database.addToDatabase("fs_foodsaver", params);

    if (params.bezirk_id) {
      await this.addRegionMember(params.bezirk_id, params.id);
    }

    return params;
  }

  private async hashPassword(password: string): Promise<string> {
    return await argon2.hash(password, {
      type: argon2.argon2i,
      timeCost: 4,
    });
  }

  /**
   * Convert a Date object or string to a formatted string
   * @param date Date object or string to convert
   * @returns Formatted date string or null if input is null
   */
  private toDateTime(date: Date | string | null): string | null {
    if (!date) return null;
    return DateTime.fromJSDate(new Date(date))
      .setZone("Europe/Berlin")
      .toFormat("yyyy-MM-dd HH:mm:ss");
  }

  /**
   * Create a new foodsaver with the role of a foodsharer
   * @param pass Password to use for the foodsaver
   * @param extraParams Additional parameters to add to the foodsaver
   * @returns The created foodsaver
   */
  async createFoodsaver(
    pass: string = null,
    extraParams: any = {},
  ): Promise<any> {
    const params = {
      verified: 1,
      rolle: 1,
      quiz_rolle: 1,
      ...extraParams,
    };
    const foodsaver = await this.createFoodsharer(pass, params);
    await this.createQuizTry(foodsaver.id, 1, 1);
    return foodsaver;
  }

  /**
   * Creates a new store coordinator with required permissions and settings
   * @param pass Password to use for the store coordinator
   * @param extraParams Additional parameters to add to the store coordinator
   * @returns The created store coordinator with associated mailbox
   */
  async createStoreCoordinator(
    pass: string = null,
    extraParams: any = {},
  ): Promise<any> {
    if (!("bezirk_id" in extraParams)) {
      const region = await this.createRegion();
      extraParams.bezirk_id = region.id;
    }

    const params = {
      rolle: 2,
      quiz_rolle: 2,
      ...extraParams,
    };

    const coordinator = await this.createFoodsaver(pass, params);
    await this.createQuizTry(coordinator.id, 2, 1);

    // Create mailbox and assign to user
    const mailbox = await this.createMailbox(
      coordinator.name[0].toLowerCase() + "." + coordinator.nachname,
    );
    await Database.connect().then((conn) =>
      conn.execute("UPDATE fs_foodsaver SET mailbox_id = ? WHERE id = ?", [
        mailbox.id,
        coordinator.id,
      ]),
    );

    return coordinator;
  }

  /**
   * Creates a new ambassador with all required permissions
   * @param pass Password to use for the ambassador
   * @param extraParams Additional parameters to add to the ambassador
   * @returns The created ambassador with associated permissions
   */
  async createAmbassador(
    pass: string = null,
    extraParams: any = {},
  ): Promise<any> {
    const params = {
      rolle: 3,
      quiz_rolle: 3,
      ...extraParams,
    };
    const ambassador = await this.createStoreCoordinator(pass, params);
    await this.createQuizTry(ambassador.id, 3, 1);
    return ambassador;
  }

  /**
   * Creates a new orga member or admin with full permissions
   * @param pass Password to use for the orga member
   * @param isAdmin Whether to create an admin (role 5) or orga member (role 4)
   * @param extraParams Additional parameters to add to the orga member
   * @returns The created orga member with associated permissions
   */
  async createOrga(
    pass: string = null,
    isAdmin: boolean = false,
    extraParams: any = {},
  ): Promise<any> {
    const params = {
      rolle: isAdmin ? 5 : 4,
      ...extraParams,
    };
    return this.createAmbassador(pass, params);
  }

  /**
   * Creates a new store with random but realistic data
   * @param bezirkId ID of the region this store belongs to
   * @param teamConversation ID of the team chat or null
   * @param springerConversation ID of the substitute chat or null
   * @param extraParams Additional parameters to add to the store
   * @returns The created store
   */
  async createStore(
    bezirkId: number,
    teamConversation: number = null,
    springerConversation: number = null,
    extraParams: any = {},
  ): Promise<any> {
    // Get store category if needed
    let storeCategoryId = null;
    if (Math.random() > 0.66) {
      // One third of stores get a category
      const [rows] = await Database.connect().then((conn) =>
        conn.execute("SELECT id FROM fs_betrieb_kategorie"),
      );
      const categories = (rows as any[]).map((row) => row.id);
      if (categories.length > 0) {
        storeCategoryId =
          categories[Math.floor(Math.random() * categories.length)];
      }
    }

    const params = {
      betrieb_status_id: Math.floor(Math.random() * 5), // CooperationStatus cases
      status: 1,
      added: this.toDateTime(faker.date.past()),
      betrieb_kategorie_id: storeCategoryId,
      plz: faker.location.zipCode(),
      stadt: faker.location.city(),
      str: faker.location.streetAddress(),
      lat: faker.location.latitude({ min: 46, max: 55 }),
      lon: faker.location.longitude({ min: 4, max: 16 }),
      name: "betrieb_" + faker.company.name(),
      status_date: this.toDateTime(faker.date.past()),
      ansprechpartner: faker.person.fullName(),
      telefon: faker.phone.number(),
      fax: faker.phone.number(),
      email: faker.internet.email(),
      begin: this.toDateTime(faker.date.past()),
      besonderheiten: "",
      public_info: "",
      public_time: 0,
      ueberzeugungsarbeit: 0,
      presse: 0,
      sticker: 0,
      abholmenge: faker.number.int({ min: 0, max: 7 }),
      team_status: 1,
      prefetchtime: 1209600,
      bezirk_id: bezirkId,
      team_conversation_id: teamConversation,
      springer_conversation_id: springerConversation,
      kette_id: 0,
      ...extraParams,
    };

    // Insert store
    params.id = await Database.addToDatabase("fs_betrieb", params);
    return params;
  }

  async addStoreTeam(
    storeId: number,
    foodsaverId: number | number[],
    isCoordinator: boolean = false,
    isWaiting: boolean = false,
    isConfirmed: boolean = true,
  ): Promise<void> {
    const addMember = async (fsId: number) => {
      const memberStatus = isConfirmed ? (isWaiting ? 2 : 1) : 0; // STATUS enum values
      const params = {
        betrieb_id: storeId,
        foodsaver_id: fsId,
        active: memberStatus,
        verantwortlich: isCoordinator ? 1 : 0,
      };

      // Check if already exists
      const exists = await Database.seeInDatabase("fs_betrieb_team", {
        betrieb_id: storeId,
        foodsaver_id: fsId,
      });

      if (!exists) {
        await Database.addToDatabase("fs_betrieb_team", params);
      }
    };

    if (Array.isArray(foodsaverId)) {
      for (const id of foodsaverId) {
        await addMember(id);
      }
    } else {
      await addMember(foodsaverId);
    }
  }

  async addCollector(
    user: number,
    store: number,
    extraParams: any = {},
  ): Promise<any> {
    const params = {
      foodsaver_id: user,
      betrieb_id: store,
      date: faker.date.recent(),
      confirmed: 1,
      ...extraParams,
    };
    params.date = this.toDateTime(params.date);

    const exists = await Database.seeInDatabase("fs_abholer", params);
    if (!exists) {
      params.id = await Database.addToDatabase("fs_abholer", params);
    }

    return params;
  }

  async createMailbox(
    name: string = null,
    fillMailbox: boolean = true,
  ): Promise<any> {
    if (!name) {
      name = faker.internet.username();
      let counter = 1;
      while (
        (await Database.seeInDatabase("fs_mailbox", { name })) &&
        counter <= 100
      ) {
        name = `${faker.internet.username()}_${counter}`;
        counter++;
      }
      if (counter > 100) {
        throw new Error(
          "Unable to generate unique mailbox name after 100 attempts",
        );
      }
    }

    const mailbox = {
      name,
      id: await Database.addToDatabase("fs_mailbox", { name }),
    };

    if (fillMailbox) {
      // Add emails to different folders
      const folders = [0, 1, 2]; // INBOX, SENT, TRASH
      for (const folder of folders) {
        const numMails = faker.number.int({ min: 10, max: 20 });
        for (let i = 0; i < numMails; i++) {
          await this.createEmail(mailbox, folder);
        }
      }
    }

    return mailbox;
  }

  async createEmail(
    mailbox: any,
    folder: number,
    extraParams: any = {},
  ): Promise<number> {
    const body = faker.lorem.paragraphs(2);
    const params = {
      mailbox_id: mailbox.id,
      folder,
      subject: faker.lorem.sentence(),
      body,
      body_html: body,
      time: this.toDateTime(faker.date.recent()),
      attach: "[]",
      read: folder === 0 ? faker.datatype.boolean() : true,
      answer: false,
      ...extraParams,
    };

    // Handle sender and receiver based on folder
    if (folder === 0) {
      // INBOX
      params.sender = JSON.stringify(
        this.createRandomEmailAddress(faker.datatype.boolean()),
      );
      params.to = JSON.stringify([
        this.createFoodsharingEmailAddress(mailbox),
        ...Array(faker.number.int({ min: 0, max: 3 }))
          .fill(null)
          .map(() => this.createRandomEmailAddress(false)),
      ]);
    } else {
      params.to = JSON.stringify(
        Array(faker.number.int({ min: 1, max: 5 }))
          .fill(null)
          .map(() => this.createRandomEmailAddress(false)),
      );
      params.sender = JSON.stringify(
        this.createFoodsharingEmailAddress(mailbox),
      );
    }

    return await Database.addToDatabase("fs_mailbox_message", params);
  }

  /**
   * Creates a new region with associated mailbox
   * @param name Name of the region (random if null)
   * @param extraParams Additional parameters to add to the region
   * @param fillMailbox Whether to populate the mailbox with test emails
   * @returns The created region with mailbox
   */
  async createRegion(
    name: string = null,
    extraParams: any = {},
    fillMailbox: boolean = true,
  ): Promise<any> {
    if (!name) {
      name = faker.person.lastName() + "-region";
    }

    const params = {
      parent_id: RegionIDs.EUROPE,
      name,
      type: UnitType.PART_OF_TOWN,
      ...extraParams,
    };

    const email = extraParams.email;
    delete params.email;

    params.id = await Database.addToDatabase("fs_bezirk", params);

    const mailbox = await this.createMailbox(
      email || `region-${params.id}`,
      fillMailbox,
    );

    await Database.connect().then((conn) =>
      conn.execute("UPDATE fs_bezirk SET mailbox_id = ? WHERE id = ?", [
        mailbox.id,
        params.id,
      ]),
    );

    // Add to closure table for hierarchies
    await Database.connect().then((conn) =>
      conn.execute(
        `
        INSERT INTO fs_bezirk_closure (ancestor_id, bezirk_id, depth)
        SELECT t.ancestor_id, ?, t.depth+1 
        FROM fs_bezirk_closure AS t 
        WHERE t.bezirk_id = ?
        UNION ALL SELECT ?, ?, 0
      `,
        [params.id, params.parent_id, params.id, params.id],
      ),
    );

    return params;
  }

  private createFoodsharingEmailAddress(mailbox: {
    name: string;
  }): EmailAddress {
    return {
      host: "foodsharing.network",
      mailbox: mailbox.name,
      personal: `${mailbox.name}@foodsharing.network`,
    };
  }

  private createRandomEmailAddress(
    includePersonal: boolean = true,
  ): EmailAddress {
    return {
      host: faker.internet.domainName(),
      mailbox: faker.internet.username(),
      personal: includePersonal ? faker.person.fullName() : null,
    };
  }

  /**
   * Creates a forum thread in a region
   * @param regionId ID of the region this thread belongs to
   * @param fsId ID of the thread creator
   * @param isAmbassadorThread Whether this is an ambassador-only thread
   * @param extraParams Additional parameters to add to the thread
   * @returns The created thread with its first post
   */
  async addForumThread(
    regionId: number,
    fsId: number,
    isAmbassadorThread: boolean = false,
    extraParams: any = {},
  ): Promise<any> {
    const params = {
      foodsaver_id: fsId,
      name: faker.lorem.sentence(),
      time: this.toDateTime(faker.date.recent()),
      active: 1,
      status: 0,
      ...extraParams,
    };

    const threadId = await Database.addToDatabase("fs_theme", params);

    await Database.addToDatabase("fs_bezirk_has_theme", {
      theme_id: threadId,
      bezirk_id: regionId,
      bot_theme: isAmbassadorThread ? 1 : 0,
    });

    const postParams = {
      body: faker.lorem.paragraphs(3),
      time: params.time,
    };

    params.post = await this.addForumThreadPost(threadId, fsId, postParams);
    params.id = threadId;

    return params;
  }

  async addForumThreadPost(
    threadId: number,
    fsId: number,
    extraParams: any = {},
  ): Promise<any> {
    const params = {
      theme_id: threadId,
      foodsaver_id: fsId,
      body: faker.lorem.text(),
      time: this.toDateTime(faker.date.recent()),
      ...extraParams,
    };

    params.id = await Database.addToDatabase("fs_theme_post", params);
    await this.updateForumThreadWithPost(threadId, params);
    return params;
  }

  private async updateForumThreadWithPost(
    threadId: number,
    post: any,
  ): Promise<void> {
    const lastPostId = await Database.grabFromDatabase(
      "fs_theme",
      "last_post_id",
      { id: threadId },
    );
    const lastPostDate = new Date(
      await Database.grabFromDatabase("fs_theme_post", "time", {
        id: lastPostId,
      }),
    );
    const thisPostDate = new Date(post.time);

    if (lastPostDate >= thisPostDate) {
      await Database.connect().then((conn) =>
        conn.execute("UPDATE fs_theme SET last_post_id = ? WHERE id = ?", [
          post.id,
          threadId,
        ]),
      );
    }
  }

  /**
   * Creates a new conversation between users
   * @param users Array of user IDs to include in the conversation
   * @param extraParams Additional parameters to add to the conversation
   * @returns The created conversation
   */
  async createConversation(
    users: number[],
    extraParams: any = {},
  ): Promise<any> {
    const params = {
      locked: 0,
      name: null,
      last: this.toDateTime(faker.date.recent()),
      last_foodsaver_id: users?.length ? users[0] : null,
      last_message_id: null,
      last_message: null,
      ...extraParams,
    };

    const id = await Database.addToDatabase("fs_conversation", params);

    for (const user of users) {
      await this.addUserToConversation(user, id);
    }

    params.id = id;
    return params;
  }

  async addUserToConversation(
    userId: number,
    conversationId: number,
    extraParams: any = {},
  ): Promise<any> {
    const params = {
      foodsaver_id: userId,
      conversation_id: conversationId,
      unread: 0,
      ...extraParams,
    };

    params.id = await Database.addToDatabase(
      "fs_foodsaver_has_conversation",
      params,
    );
    return params;
  }

  async createQuizTry(
    fsId: number,
    level: number,
    status: number,
    daysAgo: number = 0,
  ): Promise<void> {
    const startTime = DateTime.now().minus({ days: daysAgo });
    // if quizID does not exist, create it
    const exists = await Database.seeInDatabase("fs_quiz", { id: level });
    if (!exists) {
      await this.createQuiz(level);
    }
    await Database.addToDatabase("fs_quiz_session", {
      quiz_id: level,
      status: status,
      foodsaver_id: fsId,
      time_start: startTime.toFormat("yyyy-MM-dd HH:mm:ss"),
    });
  }

  async addVerificationHistory(
    userId: number,
    ambassadorId: number,
    verified: boolean,
    date: Date = null,
  ): Promise<void> {
    if (!date) {
      date = faker.date.recent();
    }

    await Database.addToDatabase("fs_verify_history", {
      fs_id: userId,
      date: this.toDateTime(date),
      bot_id: ambassadorId,
      change_status: verified ? 1 : 0,
    });
  }

  async addPassHistory(
    userId: number,
    ambassadorId: number,
    date: Date = null,
  ): Promise<void> {
    if (!date) {
      date = faker.date.recent();
    }

    await Database.addToDatabase("fs_pass_gen", {
      foodsaver_id: userId,
      date: this.toDateTime(date),
      bot_id: ambassadorId,
    });
  }

  /**
   * Creates a food basket offered by a user
   * @param userId ID of the user offering the basket
   * @param extraParams Additional parameters to add to the food basket
   * @returns The created food basket
   */
  async createFoodbasket(userId: number, extraParams: any = {}): Promise<any> {
    const params = {
      foodsaver_id: userId,
      status: 1,
      time: this.toDateTime(faker.date.recent()),
      until: this.toDateTime(faker.date.future({ days: 14 })),
      fetchtime: null,
      description: faker.lorem.paragraphs(2),
      picture: null,
      tel: faker.phone.number(),
      handy: faker.phone.number(),
      contact_type: 1,
      location_type: 0,
      weight: faker.number.int({ min: 1, max: 100 }),
      lat: faker.location.latitude({ min: 46, max: 55 }),
      lon: faker.location.longitude({ min: 4, max: 16 }),
      bezirk_id: 0,
      ...extraParams,
    };

    params.id = await Database.addToDatabase("fs_basket", params);
    return params;
  }

  async addBells(users: any[], extraParams: any = {}): Promise<number> {
    const params = {
      name: "title",
      body: faker.lorem.sentences(2),
      vars: "",
      attr: JSON.stringify({ href: "/" }),
      icon: "icon",
      identifier: "",
      time: this.toDateTime(faker.date.recent()),
      closeable: 1,
      ...extraParams,
    };

    const bellId = await Database.addToDatabase("fs_bell", params);

    for (const user of users) {
      await Database.addToDatabase("fs_foodsaver_has_bell", {
        foodsaver_id: user.id,
        bell_id: bellId,
        seen: 0,
      });
    }

    return bellId;
  }

  async createStoreCategories(): Promise<void> {
    const categories = [
      "Bäckerei",
      "Bio-Bäckerei",
      "Bio-Supermarkt",
      "Getränkemarkt",
      "Metzgerei",
      "Organisation - Einführungsabholungen",
      "Organisation - Botschaftertätigkeit",
      "Organisation - Fairteiler",
      "Organisation - Vorstandsarbeit",
      "Organisation - Meldebearbeitung",
      "Öffentlichkeitsarbeit",
      "Restaurant",
      "Schnellimbiss",
      "Supermarkt",
      "Wochenmarkt",
    ];

    for (let i = 0; i < categories.length; i++) {
      await Database.addToDatabase("fs_betrieb_kategorie", {
        id: i + 1,
        name: categories[i],
      });
    }
  }

  async createQuiz(quizId: number, questionCount: number = null): Promise<any> {
    questionCount = questionCount ?? Math.floor(Math.random() * 4) + 3; // 3-6 questions
    const questionCountUntimed = quizId === 1 ? 2 + questionCount : null;

    const conn = await Database.connect();

    // Set name and description based on quiz type
    let name = "";
    let description = "";
    switch (quizId) {
      case Role.FOODSAVER:
        name = "Quiz für Foodsaver";
        description = "Werde Foodsaver mit diesem Quiz!";
        break;
      // ...existing cases...
    }

    description += " " + faker.lorem.paragraphs(2);

    // Clear existing related data first (these don't have race condition issues)
    await conn.execute("DELETE FROM fs_quiz_session WHERE quiz_id = ?", [
      quizId,
    ]);
    await conn.execute("DELETE FROM fs_question_has_quiz WHERE quiz_id = ?", [
      quizId,
    ]);

    // Use REPLACE INTO for atomic upsert - handles concurrent quiz creation
    const quizSQL = `
      REPLACE INTO fs_quiz 
      (id, name, \`desc\`, is_desc_htmlentity_encoded, maxfp, questcount, questcount_untimed)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    `;

    await conn.execute(quizSQL, [
      quizId,
      name,
      description,
      1, // is_desc_htmlentity_encoded
      2, // maxfp
      questionCount,
      questionCountUntimed,
    ]);

    // Assertion: Quiz should exist
    const quizExists = await Database.seeInDatabase("fs_quiz", { id: quizId });
    if (!quizExists) {
      throw new Error(`Quiz with ID ${quizId} was not created correctly!`);
    }

    // Then create the questions
    const questions = [];
    for (let i = 1; i <= questionCount; i++) {
      questions.push(await this.createQuestion(quizId));
    }

    return {
      id: quizId,
      name,
      desc: description,
      is_desc_htmlentity_encoded: 1,
      maxfp: 2,
      questcount: questionCount,
      questcount_untimed: questionCountUntimed,
      questions,
    };
  }

  /**
   * Ensure a set of predefined quizzes exist in the test database.
   *
   * This method verifies that the quizzes identified by the QuizID enum
   * values are present in the "fs_quiz" table and creates any that are
   * missing. It is safe to call multiple times (idempotent) and is intended
   * to be used as test setup.
   */
  private async createQuizes(): Promise<void> {
    const wantQuizes = Object.values(QuizID).filter(
      (value) => typeof value === "number",
    ) as number[];
    const haveQuizes = await Database.grabColumnFromDatabase("fs_quiz", "id");

    for (const quizId of wantQuizes) {
      if (!haveQuizes.includes(quizId)) {
        await this.createQuiz(quizId);
      }
    }
  }

  private async createQuestion(quizId: number): Promise<any> {
    // Only include fields that exist in database
    const dbParams = {
      text: faker.lorem.paragraphs(2),
      duration: Math.floor(Math.random() * 4 + 3) * 10,
      wikilink: "https://wiki.foodsharing.de/" + faker.lorem.slug(),
    };

    const questionId = await Database.addToDatabase("fs_question", dbParams);

    const quizLinkParams = {
      question_id: questionId,
      quiz_id: quizId,
      fp: Math.floor(Math.random() * 3) + 1,
    };
    try {
      await Database.addToDatabase("fs_question_has_quiz", quizLinkParams);
    } catch (err) {
      console.error(
        `Error linking question ${questionId} to quiz ${quizId}:`,
        err.message,
      );
      throw err;
    }

    // Create answers
    const answers = [];
    const numAnswers = Math.floor(Math.random() * 4) + 2; // 2-5 answers
    for (let i = 0; i < numAnswers; i++) {
      answers.push(await this.createAnswer(questionId));
    }

    return {
      id: questionId,
      text: dbParams.text,
      duration: dbParams.duration,
      wikilink: dbParams.wikilink,
      answers,
    };
  }

  private async createAnswer(questionId: number): Promise<any> {
    const rightValue = Math.floor(Math.random() * 3);
    const rightName = ["WRONG", "CORRECT", "MAYBE"][rightValue];

    // Use direct SQL with backticks to escape the 'right' keyword
    const conn = await Database.connect();
    const [result] = await conn.execute(
      "INSERT INTO fs_answer (question_id, text, explanation, `right`) VALUES (?, ?, ?, ?)",
      [
        questionId,
        `${faker.lorem.sentence()} (${rightName})`,
        `Diese Antwort ist ${rightName}. ${faker.lorem.paragraph()}`,
        rightValue,
      ],
    );

    return {
      id: (result as any).insertId,
      question_id: questionId,
      text: `${faker.lorem.sentence()} (${rightName})`,
      explanation: `Diese Antwort ist ${rightName}. ${faker.lorem.paragraph()}`,
      right: rightValue,
    };
  }

  async addRegionAdmin(regionId: number, fsId: number): Promise<void> {
    const params = {
      bezirk_id: regionId,
      foodsaver_id: fsId,
    };

    const exists = await Database.seeInDatabase("fs_botschafter", params);
    if (!exists) {
      await Database.addToDatabase("fs_botschafter", params);
    }
  }

  async addRegionMember(
    regionId: number,
    fsId: number | number[],
    isActive: boolean = true,
  ): Promise<void> {
    if (Array.isArray(fsId)) {
      for (const id of fsId) {
        await this.addRegionMember(regionId, id, isActive);
      }
    } else {
      const params = {
        bezirk_id: regionId,
        foodsaver_id: fsId,
        active: isActive ? 1 : 0,
      };

      const exists = await Database.seeInDatabase(
        "fs_foodsaver_has_bezirk",
        params,
      );
      if (!exists) {
        await Database.addToDatabase("fs_foodsaver_has_bezirk", params);
      }
    }
  }

  async addStoreNotiz(
    userId: number,
    storeId: number,
    extraParams: any = {},
  ): Promise<any> {
    const params = {
      foodsaver_id: userId,
      betrieb_id: storeId,
      milestone: 0,
      text: faker.lorem.paragraph(),
      zeit: this.toDateTime(faker.date.recent()),
      last: 0,
      ...extraParams,
    };

    params.id = await Database.addToDatabase("fs_betrieb_notiz", params);
    return params;
  }

  async addPickup(storeId: number, extraParams: any = {}): Promise<any> {
    const date = faker.date.soon();
    const params = {
      betrieb_id: storeId,
      time: this.toDateTime(date),
      fetchercount: faker.number.int({ min: 1, max: 8 }),
      ...extraParams,
    };

    params.id = await Database.addToDatabase("fs_fetchdate", params);
    return params;
  }

  async addRecurringPickup(
    storeId: number,
    extraParams: any = {},
  ): Promise<any> {
    const hours = faker.number.int({ min: 0, max: 23 });
    const minutes = [
      "00",
      "05",
      "10",
      "15",
      "20",
      "25",
      "30",
      "35",
      "40",
      "45",
      "50",
      "55",
    ][faker.number.int({ min: 0, max: 11 })];

    const params = {
      betrieb_id: storeId,
      dow: faker.number.int({ min: 0, max: 6 }),
      time: `${hours.toString().padStart(2, "0")}:${minutes}:00`,
      fetcher: faker.number.int({ min: 1, max: 8 }),
      ...extraParams,
    };

    try {
      params.id = await Database.addToDatabase("fs_abholzeiten", params);
    } catch (e) {
      if (!extraParams) {
        return this.addRecurringPickup(storeId, extraParams);
      }
      throw e;
    }

    return params;
  }

  async addBuddy(
    user1: number,
    user2: number,
    confirmed: boolean = true,
  ): Promise<void> {
    await Database.addToDatabase("fs_buddy", {
      foodsaver_id: user1,
      buddy_id: user2,
      confirmed: confirmed ? 1 : 0,
    });
  }

  async addPicker(
    storeId: number,
    foodsaverId: number,
    extraParams: any = {},
  ): Promise<any> {
    const params = {
      foodsaver_id: foodsaverId,
      betrieb_id: storeId,
      date: this.toDateTime(faker.date.recent()),
      confirmed: "1",
      ...extraParams,
    };

    params.id = await Database.addToDatabase("fs_abholer", params);
    return params;
  }

  async addStoreLog(
    storeId: number,
    foodsaverIdA: number,
    foodsaverIdP: number,
    action: string,
    extraParams: any = {},
  ): Promise<any> {
    const params = {
      store_id: storeId,
      fs_id_a: foodsaverIdA,
      fs_id_p: foodsaverIdP,
      action: action,
      date_activity: this.toDateTime(faker.date.recent()),
      ...extraParams,
    };

    if (params.date_reference) {
      params.date_reference = this.toDateTime(params.date_reference);
    }

    params.id = await Database.addToDatabase("fs_store_log", params);
    return params;
  }

  async addStoreFoodType(extraParams: any = {}): Promise<any> {
    const params = {
      name: "food_" + faker.word.sample(),
      ...extraParams,
    };

    params.id = await Database.addToDatabase("fs_lebensmittel", params);
    return params;
  }

  async addStoreChain(extraParams: any = {}): Promise<any> {
    const params = {
      name: "chain_" + faker.company.name(),
      headquarters_zip: faker.location.zipCode(),
      headquarters_city: faker.location.city(),
      status: faker.number.int({ min: 0, max: 2 }),
      modification_date: this.toDateTime(faker.date.recent()),
      allow_press: faker.number.int({ min: 0, max: 1 }),
      notes: faker.lorem.paragraph(),
      common_store_information: faker.lorem.paragraphs(3),
      ...extraParams,
    };

    params.id = await Database.addToDatabase("fs_chain", params);
    return params;
  }

  async createWorkingGroup(name: string, extraParams: any = {}): Promise<any> {
    const params = {
      parent_id: RegionIDs.GLOBAL_WORKING_GROUPS,
      type: UnitType.WORKING_GROUP,
      teaser: "an autogenerated working group without a description",
      ...extraParams,
    };

    return this.createRegion(name, params);
  }

  /**
   * Adds a email domain to the blacklist
   * @param email The email domain to blacklist
   * @returns The ID of the created blacklist entry
   */
  async createBlacklistedEmailAddress(
    email: string = "bad.com",
  ): Promise<number> {
    return await Database.addToDatabase("fs_email_blacklist", {
      email: email,
      since: "2010-10-14 12:00:00",
      reason: "Disposable email addresses should not be used for registration.",
    });
  }

  async addConversationMessage(
    userId: number,
    conversationId: number,
    extraParams: any = {},
  ): Promise<any> {
    const params = {
      foodsaver_id: userId,
      conversation_id: conversationId,
      body: faker.lorem.paragraph(),
      time: this.toDateTime(faker.date.recent()),
      ...extraParams,
    };

    params.id = await Database.addToDatabase("fs_msg", params);

    // Update conversation with last message
    await Database.connect().then((conn) =>
      conn.execute(
        `
        UPDATE fs_conversation 
        SET last_message = ?,
            last_message_id = ?,
            last_foodsaver_id = ?,
            last = ?
        WHERE id = ?
      `,
        [params.body, params.id, userId, params.time, conversationId],
      ),
    );

    return params;
  }

  async createFoodSharePoint(
    userId: number,
    bezirkId: number = null,
    extraParams: any = {},
  ): Promise<any> {
    if (bezirkId === null) {
      bezirkId = (await this.createRegion()).id;
    }

    const params = {
      bezirk_id: bezirkId,
      name: faker.location.city(),
      desc: faker.lorem.paragraph(),
      status: 1,
      anschrift: faker.location.streetAddress(),
      plz: faker.location.zipCode(),
      ort: faker.location.city(),
      lat: faker.location.latitude({ min: 46, max: 55 }),
      lon: faker.location.longitude({ min: 4, max: 16 }),
      add_date: this.toDateTime(faker.date.recent()),
      add_foodsaver: userId,
      ...extraParams,
    };

    params.id = await Database.addToDatabase("fs_fairteiler", params);
    await this.addFoodSharePointAdmin(userId, params.id);

    return params;
  }

  async addFoodSharePointFollower(
    userId: number,
    foodSharePointId: number,
    extraParams: any = {},
  ): Promise<any> {
    const params = {
      fairteiler_id: foodSharePointId,
      foodsaver_id: userId,
      type: 0, // FOLLOWER
      infotype: 1, // EMAIL
      ...extraParams,
    };
    await Database.addToDatabase("fs_fairteiler_follower", params);
    return params;
  }

  async addFoodSharePointAdmin(
    userId: number,
    foodSharePointId: number,
    extraParams: any = {},
  ): Promise<any> {
    return this.addFoodSharePointFollower(userId, foodSharePointId, {
      ...extraParams,
      type: 1,
    }); // FOOD_SHARE_POINT_MANAGER
  }

  async addFoodSharePointPost(
    userId: number,
    foodSharePointId: number,
    extraParams: any = {},
  ): Promise<any> {
    const post = await this.createWallpost(userId, extraParams);
    await Database.addToDatabase("fs_fairteiler_has_wallpost", {
      fairteiler_id: foodSharePointId,
      wallpost_id: post.id,
    });
    return post;
  }

  async createWallpost(userId: number, extraParams: any = {}): Promise<any> {
    const params = {
      foodsaver_id: userId,
      body: faker.lorem.paragraph(),
      time: this.toDateTime(faker.date.recent()),
      ...extraParams,
    };
    params.id = await Database.addToDatabase("fs_wallpost", params);
    return params;
  }

  async createCommunityPin(
    regionId: number,
    extraParams: any = {},
  ): Promise<any> {
    const params = {
      region_id: regionId,
      lat: faker.location.latitude({ min: 46, max: 55 }),
      lon: faker.location.longitude({ min: 4, max: 16 }),
      desc: faker.lorem.paragraph(),
      status: 1, // ACTIVE
      ...extraParams,
    };
    params.id = await Database.addToDatabase("fs_region_pin", params);
    return params;
  }

  async createEvents(
    regionId: number,
    foodsaverId: number,
    extraParams: any = {},
  ): Promise<any> {
    const locationParams = {
      name: faker.lorem.words(3),
      lat: faker.location.latitude({ min: 46, max: 55 }),
      lon: faker.location.longitude({ min: 4, max: 16 }),
    };

    const locationId = await Database.addToDatabase(
      "fs_location",
      locationParams,
    );

    const params = {
      bezirk_id: regionId,
      foodsaver_id: foodsaverId,
      location_id: locationId,
      public: faker.number.int({ min: 0, max: 2 }),
      name: faker.lorem.sentence(),
      start: this.toDateTime(faker.date.soon({ days: 1 })),
      end: this.toDateTime(faker.date.soon({ days: 2 })),
      description: faker.lorem.paragraphs(2),
      ...extraParams,
    };

    params.id = await Database.addToDatabase("fs_event", params);

    await Database.addToDatabase("fs_foodsaver_has_event", {
      foodsaver_id: foodsaverId,
      event_id: params.id,
    });

    return params;
  }

  async addEventInvitation(
    eventId: number,
    foodsaverId: number,
    extraParams: any = {},
  ): Promise<any> {
    const params = {
      foodsaver_id: foodsaverId,
      event_id: eventId,
      ...extraParams,
    };
    params.id = await Database.addToDatabase("fs_foodsaver_has_event", params);
    return params;
  }

  async addBlogPost(
    authorId: number,
    regionId: number,
    extraParams: any = {},
  ): Promise<any> {
    const params = {
      bezirk_id: regionId,
      foodsaver_id: authorId,
      name: faker.lorem.words(5),
      body: faker.lorem.paragraphs(5),
      teaser: faker.lorem.paragraph(),
      time: this.toDateTime(faker.date.recent()),
      active: 1,
      picture: "",
      ...extraParams,
    };
    params.id = await Database.addToDatabase("fs_blog_entry", params);
    return params;
  }

  async addReport(
    reporterId: number,
    reporteeId: number,
    storeId: number = 0,
    confirmed: number = 0,
    reason: string = null,
    message: string = null,
  ): Promise<any> {
    const params = {
      reporter_id: reporterId,
      foodsaver_id: reporteeId,
      betrieb_id: storeId,
      reporttype: 1, // LOCAL type
      report_reason_id: 2,
      time: this.toDateTime(faker.date.recent()),
      msg: message ?? faker.lorem.paragraphs(2),
      tvalue: reason ?? faker.lorem.sentence(),
      committed: confirmed,
    };
    params.id = await Database.addToDatabase("fs_report", params);
    return params;
  }

  async createPoll(
    regionId: number,
    authorId: number,
    extraParams: any = {},
  ): Promise<any> {
    const params = {
      name: faker.lorem.words(5),
      description: faker.lorem.paragraphs(2),
      scope: VotingScope.FOODSAVERS,
      type: faker.helpers.arrayElement([
        VotingType.SELECT_ONE_CHOICE,
        VotingType.SELECT_MULTIPLE,
        VotingType.THUMB_VOTING,
      ]),
      author: authorId,
      region_id: regionId,
      start: this.toDateTime(faker.date.recent({ days: 7 })),
      end: this.toDateTime(faker.date.soon({ days: 7 })),
      votes: faker.number.int({ min: 0, max: 1000 }),
      eligible_votes_count: 0,
      creation_timestamp: this.toDateTime(faker.date.recent({ days: 7 })),
      ...extraParams,
    };
    params.id = await Database.addToDatabase("fs_poll", params);
    return params;
  }

  async createPollOption(
    pollId: number,
    values: number[],
    extraParams: any = {},
  ): Promise<any> {
    const optionCount = await Database.grabFromDatabase(
      "fs_poll_has_options",
      "COUNT(*) as count",
      { poll_id: pollId },
    );

    const params = {
      poll_id: pollId,
      option: parseInt(optionCount),
      option_text: faker.lorem.sentence(),
      ...extraParams,
    };

    await Database.addToDatabase("fs_poll_has_options", params);

    for (const value of values) {
      await Database.addToDatabase("fs_poll_option_has_value", {
        poll_id: pollId,
        option: params.option,
        value: value,
        votes: faker.number.int({ min: 0, max: 100 }),
      });
    }

    return params;
  }

  async addVoters(pollId: number, userIds: number[]): Promise<void> {
    for (const id of userIds) {
      await Database.addToDatabase("fs_foodsaver_has_poll", {
        poll_id: pollId,
        foodsaver_id: id,
        time: null,
      });
    }

    const previousCount = await Database.grabFromDatabase(
      "fs_poll",
      "eligible_votes_count",
      { id: pollId },
    );
    await Database.connect().then((conn) =>
      conn.execute("UPDATE fs_poll SET eligible_votes_count = ? WHERE id = ?", [
        parseInt(previousCount) + userIds.length,
        pollId,
      ]),
    );
  }

  async giveBanana(
    senderId: number,
    recipientId: number,
    message: string = null,
  ): Promise<void> {
    if (!message) {
      message = this.createRandomText(100, 300);
    }

    await Database.addToDatabase("fs_rating", {
      foodsaver_id: recipientId,
      rater_id: senderId,
      msg: message,
      time: this.toDateTime(faker.date.recent()),
    });
  }

  private createRandomText(minLength: number, maxLength: number): string {
    let text = faker.lorem.paragraph();
    while (text.length < minLength) {
      text += " " + faker.lorem.paragraph();
    }
    return text.slice(0, maxLength);
  }

  /**
   * Simplified and adjusted version of what happens in UploadTransactions::uploadFile.
   * Can be used to "upload" profile pictures for generated users.
   */
  private async uploadFile(file: UploadedFile): Promise<string> {
    const uuid = uuidv4();
    await Database.addToDatabase("uploads", {
      uuid: uuid,
      user_id: file.uploaderId,
      sha256hash: file.hashedBody,
      mimetype: file.mimeType,
      uploaded_at: this.toDateTime(new Date()),
      filesize: file.fileSize,
    });

    const pathForPersistentFile = path.join(
      process.env.ROOT_DIR || ".",
      "data/uploads",
      uuid[0],
      uuid.substring(1, 3),
      uuid,
    );

    const dir = path.dirname(pathForPersistentFile);
    await mkdirp(dir);
    await fs.promises.copyFile(file.filePath, pathForPersistentFile);

    return uuid;
  }

  private async hashFile(algorithm: string, filePath: string): Promise<string> {
    const crypto = require("crypto");
    const fileBuffer = await fs.promises.readFile(filePath);
    const hashSum = crypto.createHash(algorithm);
    hashSum.update(fileBuffer);
    return hashSum.digest("hex");
  }
}

export const foodsharing = new Foodsharing();
