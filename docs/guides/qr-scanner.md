# QR Scanner Setup and Operations

The scanner uses the maintained qr-scanner browser library for webcam and image decoding. It also accepts keyboard-emulated USB scanners and manual student/card identifiers. All successful lookups are database-backed.

## Browser and camera setup

- Use a current Chrome, Edge, Firefox, or Safari release supported by the institution.
- Production camera access requires HTTPS. Localhost is acceptable only for local testing.
- Grant camera permission only to the approved IDMS origin.
- Prefer the rear/environment camera on tablets or phones.
- Close other applications that may exclusively hold the camera.
- If access was denied, use the browser site-permission control to reset camera permission and reload.

Start camera opens the live decoder. Stop camera, page navigation, tab hiding, and page unload stop the media stream. If a camera is missing or unavailable, use image, USB, or manual input instead.

## Input methods

1. Camera: choose the operational mode, select Start camera, and place one QR code clearly in frame.
2. QR image: choose Scan QR image and select a real QR image file. Unreadable images produce an explicit error.
3. USB scanner: focus Scan or Enter ID, then scan. Keyboard-emulated scanners type the payload and normally submit with Enter.
4. Manual: enter the student number or card token and choose Verify ID.

Verification mode is always read-only. Production and Releasing modes expose only transitions allowed by the user's permission and current lifecycle state.

## Expected results

- Unknown identifiers report no database match.
- Valid identifiers display student, enrollment, lifecycle, print/release evidence, discrepancies, and transaction history.
- Duplicate rapid scans are reported and do not change lifecycle status.
- Camera/decoder failures retain manual and image alternatives.
- Release requires an eligible card and explicit acknowledgement; optional claimant data is stored with the release event.

## Physical UAT

Before production approval, test each approved workstation/device:

1. Confirm the exact browser and OS can grant camera permission over the production HTTPS origin.
2. Decode several institution-issued cards at typical distance and lighting.
3. Test front/rear camera selection where relevant.
4. Test the actual USB scanner, including Enter suffix and non-US keyboard behavior.
5. Test unreadable, damaged, unknown, duplicate, printed, ready, and released codes.
6. Navigate away during camera use and confirm the camera indicator turns off.
7. Confirm production/release buttons are absent or forbidden for unauthorized accounts.
8. Record device model, browser version, date, operator, and result.

Headless browser decoding of generated QR images has passed. Physical camera and USB hardware testing has not been claimed.