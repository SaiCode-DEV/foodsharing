# User generated content

## Uploaded files
There are several use cases for which files can be uploaded and used on the website:
- each user can have a profile picture
- a blog post can contain a picture
- food share points can have a header picture
- wall posts can contain one or more picture
- food baskets can contain one or more picture
- emails can have attachments
- resources can contain one or more picture

All uploads are limited to 1.5 MB per file. While email attachments can be any file type, all other cases are restricted to images only. All of these are optional which means that, for example, a food share point can be created without a header picture. Every uploaded file can only be used for one of the types in the list above.

### Uploading
Adding or changing a picture is always done in two steps:
1. The clients uploads the file to the `POST /api/uploads` endpoint. The server will assign a UUID to the file and store it in the `data/uploads/` directory. In addition, an entry in the [uploads database table](./backend/database#table-uploads) is created that links the UUID to the file and its meta data. The UUID is returned to the client and can be used to access the file using the `GET /api/uploads/{uuid}` endpoint. The database entry also contains the uploader's id, a usage id and a usage type. At this point, both usage values will be null which means that the uploaded file is not yet being used anywhere.
2. The client asks the server to use the previously created UUID for one of the usage types above. The API endpoint for this depends on the usage type. For example, the `PATCH /api/users/current/photo` endpoint can be used with the UUID in the request body to set the user's profile picture. The server will link the entry in the uploads database table to the profile by setting the usage type and usage id. In this example, the usage type is set to "profile photo" and the usage id is set to the corresponding profile id.

Files uploaded by a user will always have a valid uploader's id in the database entry. For security, using uploaded files is limited to the user who uploaded it. This means that the second step fails if the UUID was created by another user.

The uploader id can, however, be null in special cases:
- Pictures that we moved from the old paths by a script. This includes title pictures of food share points and working groups, in which case we do not know the original owner of the file.
- Attachments of incoming emails. These are sorted into mailboxes, which do not necessarily correspond to single users.

### Deleting
A client can not delete an uploaded file. A file and its corresponding database entry is deleted automatically by the server if
- a new uploaded file is linked to the same entity. For example, if a file is set as profile picture, the previous picture of that profile is deleted.
- the linked entity is deleted. For example, if a blog post is deleted, its corresponding picture is also deleted.
- the linked entity does not exist anymore. This should not happen because of the previous case, but for backwards compatibility it might nevertheless happen sometimes.
- their usage id and type are not set two days after upload. This can happen if the client only finished step 1 of the upload procedure. 

The first two cases happen immediately, while the last two cases are done in the nightly maintenance job. 

### Backwards compatibility
The sections above describe the ideal case and affects new uploaded files. There are, however, a few exceptions for files that were uploaded earlier. Roughly speaking, there are three versions of uploads:
- *before approximately 2020*: Before using the upload API there was a different upload endpoint for each of the use cases. The server created several resized versions of the file, each with a different prefix like '50_q_' or 'thumb_crop_'. All these files were stored in the `images/` directory. The "deleting" section above does not apply to these files at all.
- *before May 2024*: Since the uploaded API was introduced in 2020, files are stored in the way described above. This was not introduced for all use cases at the same time. Also, the database entries for these files do not have a usage type or id, which means they cannot be linked to the corresponding entity. The fourth case in the "deleting" section does not apply to these files.
- *since May 2024*: Tagging of uploaded files was introduced in release "Laugenbrezel". Files that were uploaded after this are treated as described above.

### Adding a new type
If you want to add a new usage type for uploads, please take care of the following things:

- The database column of the entity table should only contain the UUID of the uploaded file, not the full path. It should then be linked with a foreign key to the `uuid` column of the `uploads` table. If more than one file can be attached, the UUIDs should be stored comma-separated and can not be linked with a foreign key. If attaching a file is optional for the entity, the column should use null if there is no file.
- The frontend should follow the two steps described above: first upload the files, then connect them to the entity.
- The type should be added to the [UploadUsage](https://gitlab.com/foodsharing-dev/foodsharing/-/blob/master/src/Modules/Core/DBConstants/Uploads/UploadUsage.php) enum.
- The uploaded files should be marked by the backend with usage id and type as soon as the files are connected to the entity (see UploadsGateway::setUsage).
- If the entity can be deleted, the uploaded files should be deleted when the entity is deleted. This is also necessary if entities are deleted by the maintenance cron job.
- If it is possible to edit the entity and to change the attached files, the uploaded files that were replaced should be deleted.
- The backend needs to take care of permissions:

  If the entity can only be edited by one user (like profile pictures), users should only be allowed to use files (i.e. to connect them to the entity) which they uploaded themselves.

  In other cases, many users can edit the same entity (for example title pictures of groups). It then needs to be possible to edit the entity, if the file was previously uploaded by another user and not changed by the currently editing user. For example: user A and B are group admins of the same group. User A edits the group and sets a new group picture. Later, user B wants to edit the same group. User B is allowed to edit the group without changing the photo or by uploading a new photo, but is not allowed to use the UUID of someone else's upload.
