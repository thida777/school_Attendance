package com.school.attendance.config;

import android.view.View;
import android.widget.TextView;

import androidx.core.content.ContextCompat;

import com.school.attendance.R;

/**
 * Colours the step bar (layout/view_step_bar.xml): 1 Choose class · 2 Mark · 3 Review · 4 Done.
 * Steps already passed and the current step are teal; the next steps are grey.
 */
public final class StepBar {

    private static final int[] CIRCLES = {R.id.step1, R.id.step2, R.id.step3, R.id.step4};
    private static final int[] LABELS = {R.id.stepLabel1, R.id.stepLabel2, R.id.stepLabel3, R.id.stepLabel4};

    private StepBar() {
    }

    /** @param step 1 to 4 */
    public static void show(View root, int step) {
        for (int i = 0; i < CIRCLES.length; i++) {
            TextView circle = root.findViewById(CIRCLES[i]);
            TextView label = root.findViewById(LABELS[i]);
            boolean reached = i < step;
            boolean current = i == step - 1;
            circle.setBackgroundResource(reached ? R.drawable.bg_step_active : R.drawable.bg_step_todo);
            circle.setTextColor(ContextCompat.getColor(root.getContext(), reached ? R.color.on_brand : R.color.text_muted));
            // A passed step shows ✓ instead of its number
            circle.setText(reached && !current ? "✓" : String.valueOf(i + 1));
            label.setTextColor(ContextCompat.getColor(root.getContext(), current ? R.color.brand : R.color.text_muted));
            label.setTypeface(null, current ? android.graphics.Typeface.BOLD : android.graphics.Typeface.NORMAL);
        }
    }
}
