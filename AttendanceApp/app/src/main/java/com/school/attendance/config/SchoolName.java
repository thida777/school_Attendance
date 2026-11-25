package com.school.attendance.config;

import android.content.Context;
import android.content.SharedPreferences;
import android.widget.TextView;

import androidx.annotation.NonNull;

import com.school.attendance.R;
import com.school.attendance.models.SchoolResponse;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * The school name from the website's School settings (GET /school), on the start and login screens.
 * The last name received is kept on the phone, so it shows at once, and also without internet.
 */
public final class SchoolName {

    private static final String PREFS = "school";

    private SchoolName() {
    }

    /** Show the name in the view (plus an ending such as " · 2026"), then refresh it from the server. */
    public static void show(TextView view, String ending) {
        Context context = view.getContext().getApplicationContext();
        SharedPreferences prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE);
        view.setText(saved(context, prefs) + ending);

        RetrofitInstance.getAuthApi().school().enqueue(new Callback<SchoolResponse>() {
            @Override
            public void onResponse(@NonNull Call<SchoolResponse> call, @NonNull Response<SchoolResponse> response) {
                SchoolResponse body = response.body();
                if (!response.isSuccessful() || body == null || body.getNameEn() == null) return;
                prefs.edit().putString("en", body.getNameEn()).putString("km", body.getNameKm()).apply();
                view.setText(saved(context, prefs) + ending);
            }

            @Override
            public void onFailure(@NonNull Call<SchoolResponse> call, @NonNull Throwable t) {
                // No internet: keep the saved name
            }
        });
    }

    private static String saved(Context context, SharedPreferences prefs) {
        String name = prefs.getString(Config.currentLanguage(), null);
        return name != null && !name.isEmpty() ? name : context.getString(R.string.school_name);
    }
}
