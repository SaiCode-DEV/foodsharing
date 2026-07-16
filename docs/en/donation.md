# Donation

## Donation Modal

The modal is shown when:

- `showDonationModal` is active
- the user is not on the donation page or the donation admin page
- the user has not closed it recently
- the modal content could be loaded successfully

The delay before it can be shown again after closing depends on whether the user is logged in:

- logged in: `showDonationModalInHoursForLoggedInUsers`
- logged out: `showDonationModalInHoursForLoggedOutUsers`

These variables can be changed in the system administration menu under "Edit donation page".

In the donation administration, you can adjust:

1. Iframe URLs for the campaign, friendship circle, and one-time donations
2. Info URL for the donation banner modal
3. Popup URL for the donation banner modal
4. The project IDs for the campaign, friendship circle, donation banner, and one-time donation so the current Twingle donation data can be loaded
5. Whether the campaign and the donation banner are shown
6. Whether campaign part 1 and part 2 are shown
7. Whether the campaign gallery is shown
8. After how many hours the donation banner is shown again for logged-in users
9. After how many hours the donation banner is shown again for logged-out users

The donation admin page loads the available project IDs from the external Twingle API and displays the matching project names so they can be selected from a dropdown.
