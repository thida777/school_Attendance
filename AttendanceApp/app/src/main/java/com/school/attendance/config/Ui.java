package com.school.attendance.config;

import android.content.Context;
import android.view.View;
import android.widget.ImageView;
import android.widget.TextView;

import androidx.core.content.ContextCompat;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowInsetsCompat;

import com.bumptech.glide.Glide;
import com.school.attendance.R;
import com.school.attendance.models.User;

import java.text.ParseException;
import java.text.SimpleDateFormat;
import java.util.Calendar;
import java.util.Date;
import java.util.Locale;

/** Small helpers used by several screens. */
public final class Ui {

    private static final String[] KM_DAYS = {"អាទិត្យ", "ច័ន្ទ", "អង្គារ", "ពុធ", "ព្រហស្បតិ៍", "សុក្រ", "សៅរ៍"};
    private static final String[] KM_MONTHS = {"មករា", "កុម្ភៈ", "មីនា", "មេសា", "ឧសភា", "មិថុនា",
            "កក្កដា", "សីហា", "កញ្ញា", "តុលា", "វិច្ឆិកា", "ធ្នូ"};

    private Ui() {
    }

    public static boolean isKhmer() {
        return "km".equals(Config.currentLanguage());
    }

    /**
     * The app draws behind the status bar. Add the status bar's height to the top padding of a header,
     * so its text is not under the clock and battery icons.
     */
    public static void padBelowStatusBar(View header) {
        int top = header.getPaddingTop();
        ViewCompat.setOnApplyWindowInsetsListener(header, (view, insets) -> {
            Insets bars = insets.getInsets(WindowInsetsCompat.Type.systemBars());
            view.setPadding(view.getPaddingLeft(), top + bars.top, view.getPaddingRight(), view.getPaddingBottom());
            return insets;
        });
        ViewCompat.requestApplyInsets(header);
    }

    /** "2026-11-17" → "Tuesday, 17 November 2026" or "ថ្ងៃអង្គារ 17 វិច្ឆិកា 2026". */
    public static String longDate(String isoDate) {
        Date date;
        try {
            date = new SimpleDateFormat("yyyy-MM-dd", Locale.US).parse(isoDate);
        } catch (ParseException | NullPointerException e) {
            return isoDate == null ? "" : isoDate;
        }
        if (!isKhmer()) {
            return new SimpleDateFormat("EEEE, d MMMM yyyy", Locale.ENGLISH).format(date);
        }
        Calendar c = Calendar.getInstance();
        c.setTime(date);
        return "ថ្ងៃ" + KM_DAYS[c.get(Calendar.DAY_OF_WEEK) - 1] + " " + c.get(Calendar.DAY_OF_MONTH)
                + " " + KM_MONTHS[c.get(Calendar.MONTH)] + " " + c.get(Calendar.YEAR);
    }

    /**
     * The colour of an attendance rate, the same rule as the website (Risk::tone):
     * green 90% or more, orange 80–89%, red under 80%, grey when there is no data yet.
     */
    public static int rateColor(Context context, Double rate) {
        int color;
        if (rate == null) color = R.color.text_muted;
        else if (rate >= 90) color = R.color.present_text;
        else if (rate >= 80) color = R.color.warning_text;
        else color = R.color.absent_text;
        return ContextCompat.getColor(context, color);
    }

    /** "85.7%" (no ".0" for whole numbers), or "–" when there is no data yet. */
    public static String rateText(Double rate) {
        if (rate == null) return "–";
        return rate % 1 == 0 ? String.format(Locale.US, "%.0f%%", rate) : String.format(Locale.US, "%.1f%%", rate);
    }

    /**
     * Teachers see simple words for risk, like on the website's My Classes page:
     * low → Good (green), medium → Watch (orange), high / critical → At risk (red), none → No records yet.
     * Sets the text, colour and chip background of the given TextView.
     */
    public static void showRisk(TextView chip, String risk) {
        Context c = chip.getContext();
        int text, color, background;
        // The same four words as the website: Good · Watch · High risk · Critical
        if ("low".equals(risk)) {
            text = R.string.risk_good; color = R.color.present_text; background = R.drawable.bg_chip_present;
        } else if ("medium".equals(risk)) {
            text = R.string.risk_watch; color = R.color.warning_text; background = R.drawable.bg_chip_warning;
        } else if ("high".equals(risk)) {
            text = R.string.risk_high; color = R.color.absent_text; background = R.drawable.bg_chip_absent;
        } else if ("critical".equals(risk)) {
            text = R.string.risk_critical; color = R.color.absent_text; background = R.drawable.bg_chip_absent;
        } else {
            text = R.string.no_records_yet; color = R.color.text_muted; background = R.drawable.bg_chip_grey;
        }
        chip.setText(text);
        chip.setTextColor(ContextCompat.getColor(c, color));
        chip.setBackgroundResource(background);
    }

    /** A small Material chip in our colours (teal when chosen), for filters and reasons. */
    public static com.google.android.material.chip.Chip makeChip(Context c, String text) {
        com.google.android.material.chip.Chip chip = new com.google.android.material.chip.Chip(c);
        chip.setText(text);
        chip.setCheckable(true);
        chip.setChipBackgroundColor(ContextCompat.getColorStateList(c, R.color.chip_bg));
        chip.setTextColor(ContextCompat.getColorStateList(c, R.color.chip_text));
        chip.setChipStrokeColor(ContextCompat.getColorStateList(c, R.color.chip_stroke));
        chip.setChipStrokeWidth(c.getResources().getDisplayMetrics().density);
        chip.setCheckedIconVisible(false);
        chip.setTextSize(13);
        chip.setEnsureMinTouchTargetSize(false);
        return chip;
    }

    /** "sick" → "Sick" / "ឈឺ" (the reason codes come from the API). */
    public static String reasonLabel(Context context, String code) {
        if (code == null) code = "not_given";
        int text;
        switch (code) {
            case "sick": text = R.string.reason_sick; break;
            case "family_matter": text = R.string.reason_family_matter; break;
            case "school_activity": text = R.string.reason_school_activity; break;
            case "other": text = R.string.reason_other; break;
            default: text = R.string.reason_not_given;
        }
        return context.getString(text);
    }

    /** Show the teacher's photo in a circle, or their initials when there is no photo. */
    public static void showAvatar(ImageView photo, TextView initials, User user) {
        initials.setText(user.getInitials());
        String url = user.getPhotoUrl();
        if (url == null || url.isEmpty()) {
            photo.setVisibility(View.GONE);
            initials.setVisibility(View.VISIBLE);
            return;
        }
        photo.setVisibility(View.VISIBLE);
        initials.setVisibility(View.INVISIBLE);
        Glide.with(photo).load(url).circleCrop().into(photo);
    }

    /**
     * Show a student's photo in a circle over the initials, or only the initials when there is no photo.
     * grey = true for an absent student: the photo turns grey and pale, like the website.
     */
    public static void showStudentPhoto(ImageView photo, TextView initials, com.school.attendance.models.Student student, boolean grey) {
        initials.setText(student.initials());
        String url = student.getPhotoUrl();
        if (url == null || url.isEmpty()) {
            Glide.with(photo).clear(photo);
            photo.setVisibility(View.GONE);
            initials.setVisibility(View.VISIBLE);
            return;
        }
        photo.setVisibility(View.VISIBLE);
        initials.setVisibility(View.INVISIBLE);
        Glide.with(photo).load(url).circleCrop().into(photo);
        if (grey) {
            android.graphics.ColorMatrix matrix = new android.graphics.ColorMatrix();
            matrix.setSaturation(0);
            photo.setColorFilter(new android.graphics.ColorMatrixColorFilter(matrix));
            photo.setAlpha(0.6f);
        } else {
            photo.clearColorFilter();
            photo.setAlpha(1f);
        }
    }
    /** "2026-11-25T13:05:00+07:00" → "25 Nov, 13:05" or "25 វិច្ឆិកា 13:05". */
    public static String dateTimeText(String iso) {
        if (iso == null || iso.length() < 16) return iso == null ? "" : iso;
        Date date;
        try {
            date = new SimpleDateFormat("yyyy-MM-ddTHH:mm", Locale.US).parse(iso.substring(0, 16));
        } catch (ParseException e) {
            return iso;
        }
        Calendar c = Calendar.getInstance();
        c.setTime(date);
        String time = new SimpleDateFormat("HH:mm", Locale.US).format(date);
        return isKhmer()
                ? c.get(Calendar.DAY_OF_MONTH) + " " + KM_MONTHS[c.get(Calendar.MONTH)] + " " + time
                : new SimpleDateFormat("d MMM", Locale.ENGLISH).format(date) + ", " + time;
    }

    /** father / mother / guardian → the word in the app language. */
    public static String relationText(Context context, String relation) {
        if ("father".equals(relation)) return context.getString(R.string.relation_father);
        if ("mother".equals(relation)) return context.getString(R.string.relation_mother);
        if ("guardian".equals(relation)) return context.getString(R.string.relation_guardian);
        return "";
    }

    /**
     * The parent's Telegram invite as a big QR code, to show at a parents' meeting.
     * The parent scans it with the phone camera, Telegram opens the school bot, they press Start: linked.
     */
    public static void showTelegramQr(Context context, String parentName, String link) {
        int size = (int) (240 * context.getResources().getDisplayMetrics().density);
        android.graphics.Bitmap qr;
        try {
            com.google.zxing.common.BitMatrix m = new com.google.zxing.qrcode.QRCodeWriter()
                    .encode(link, com.google.zxing.BarcodeFormat.QR_CODE, size, size);
            qr = android.graphics.Bitmap.createBitmap(size, size, android.graphics.Bitmap.Config.RGB_565);
            for (int x = 0; x < size; x++) {
                for (int y = 0; y < size; y++) {
                    qr.setPixel(x, y, m.get(x, y) ? 0xFF0F2F2C : 0xFFFFFFFF);
                }
            }
        } catch (com.google.zxing.WriterException e) {
            return;
        }

        android.widget.LinearLayout box = new android.widget.LinearLayout(context);
        box.setOrientation(android.widget.LinearLayout.VERTICAL);
        box.setGravity(android.view.Gravity.CENTER_HORIZONTAL);
        int pad = (int) (16 * context.getResources().getDisplayMetrics().density);
        box.setPadding(pad, pad / 2, pad, 0);
        android.widget.ImageView image = new android.widget.ImageView(context);
        image.setImageBitmap(qr);
        image.setContentDescription(context.getString(R.string.telegram_invite));
        box.addView(image, new android.widget.LinearLayout.LayoutParams(size, size));
        android.widget.TextView steps = new android.widget.TextView(context);
        steps.setText(R.string.telegram_qr_steps);
        steps.setPadding(0, pad, 0, 0);
        steps.setTextSize(13);
        box.addView(steps);

        new com.google.android.material.dialog.MaterialAlertDialogBuilder(context)
                .setTitle(context.getString(R.string.telegram_qr_title, parentName))
                .setView(box)
                .setPositiveButton(R.string.close, null)
                .show();
    }
}
