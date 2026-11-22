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
        if ("low".equals(risk)) {
            text = R.string.risk_good; color = R.color.present_text; background = R.drawable.bg_chip_present;
        } else if ("medium".equals(risk)) {
            text = R.string.risk_watch; color = R.color.warning_text; background = R.drawable.bg_chip_warning;
        } else if ("high".equals(risk) || "critical".equals(risk)) {
            text = R.string.risk_at_risk; color = R.color.absent_text; background = R.drawable.bg_chip_absent;
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
}
