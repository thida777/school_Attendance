package com.school.attendance.fragments;

import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.content.res.Configuration;
import android.net.Uri;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.TextView;
import android.widget.Toast;

import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.PickVisualMediaRequest;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.appcompat.app.AppCompatDelegate;
import androidx.core.content.FileProvider;
import androidx.fragment.app.Fragment;

import com.google.android.material.bottomsheet.BottomSheetDialog;
import com.google.android.material.button.MaterialButtonToggleGroup;
import com.google.android.material.materialswitch.MaterialSwitch;
import com.school.attendance.R;
import com.school.attendance.activities.ChangePasswordActivity;
import com.school.attendance.activities.MainActivity;
import com.school.attendance.activities.PersonalInfoActivity;
import com.school.attendance.config.ApiError;
import com.school.attendance.config.Config;
import com.school.attendance.config.PhotoTools;
import com.school.attendance.config.RetrofitInstance;
import com.school.attendance.config.Session;
import com.school.attendance.config.Ui;
import com.school.attendance.models.ApiMessage;
import com.school.attendance.models.ClassListResponse;
import com.school.attendance.models.LocaleRequest;
import com.school.attendance.models.SchoolClass;
import com.school.attendance.models.User;
import com.school.attendance.models.UserResponse;

import java.io.File;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

import okhttp3.MultipartBody;
import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * Profile tab: photo (gallery / camera / remove), 3 numbers, language, dark mode,
 * personal information, change password, sign out.
 */
public class ProfileFragment extends Fragment {

    private TextView txtInitials, txtName, txtOtherName, txtContact, statClasses, statStudents, statRate;
    private ImageView imgPhoto;
    private View photoProgress, btnCamera;
    private Uri cameraUri;
    private final ExecutorService background = Executors.newSingleThreadExecutor();

    // Android's photo picker (no permission needed) and the camera app
    private final ActivityResultLauncher<PickVisualMediaRequest> pickPhoto =
            registerForActivityResult(new ActivityResultContracts.PickVisualMedia(), uri -> {
                if (uri != null) upload(uri);
            });
    private final ActivityResultLauncher<Uri> takePhoto =
            registerForActivityResult(new ActivityResultContracts.TakePicture(), saved -> {
                if (Boolean.TRUE.equals(saved) && cameraUri != null) upload(cameraUri);
            });

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        return inflater.inflate(R.layout.fragment_profile, container, false);
    }

    @Override
    public void onViewCreated(@NonNull View view, @Nullable Bundle savedInstanceState) {
        Ui.padBelowStatusBar(view.findViewById(R.id.profileHeader));

        txtInitials = view.findViewById(R.id.txtInitials);
        imgPhoto = view.findViewById(R.id.imgPhoto);
        photoProgress = view.findViewById(R.id.photoProgress);
        btnCamera = view.findViewById(R.id.btnCamera);
        txtName = view.findViewById(R.id.txtName);
        txtOtherName = view.findViewById(R.id.txtOtherName);
        txtContact = view.findViewById(R.id.txtContact);
        statClasses = view.findViewById(R.id.statClasses);
        statStudents = view.findViewById(R.id.statStudents);
        statRate = view.findViewById(R.id.statRate);
        ((TextView) view.findViewById(R.id.txtVersion)).setText(getString(R.string.app_name) + " " + appVersion());

        btnCamera.setOnClickListener(v -> showPhotoOptions());
        view.findViewById(R.id.avatarBox).setOnClickListener(v -> showPhotoOptions());
        view.findViewById(R.id.rowPersonal).setOnClickListener(v -> startActivity(new Intent(requireContext(), PersonalInfoActivity.class)));
        view.findViewById(R.id.rowPassword).setOnClickListener(v -> startActivity(new Intent(requireContext(), ChangePasswordActivity.class)));
        view.findViewById(R.id.btnSignOut).setOnClickListener(v -> confirmSignOut());

        setUpLanguage(view.findViewById(R.id.toggleLanguage));
        setUpDarkMode(view.findViewById(R.id.switchDark));

        showTeacher();
        loadNumbers();
    }

    private void showTeacher() {
        User user = Session.getUser();
        if (user == null) return;
        boolean khmer = Ui.isKhmer();
        txtName.setText(user.displayName(khmer));
        String other = user.otherName(khmer);
        txtOtherName.setText(other);
        txtOtherName.setVisibility(other == null || other.isEmpty() ? View.GONE : View.VISIBLE);
        String contact = user.getPhoneFormatted();
        if (user.getEmail() != null && !user.getEmail().isEmpty()) contact += " · " + user.getEmail();
        txtContact.setText(contact);
        Ui.showAvatar(imgPhoto, txtInitials, user);
    }

    /** "1.0" from build.gradle (versionName). */
    private String appVersion() {
        try {
            return requireContext().getPackageManager().getPackageInfo(requireContext().getPackageName(), 0).versionName;
        } catch (Exception e) {
            return "";
        }
    }

    // ---------- 3 numbers: classes, students, average rate (GET /classes) ----------

    private void loadNumbers() {
        RetrofitInstance.getTeachingApi().classes().enqueue(new Callback<ClassListResponse>() {
            @Override
            public void onResponse(@NonNull Call<ClassListResponse> call, @NonNull Response<ClassListResponse> response) {
                if (!isAdded() || ApiError.sessionEnded(getActivity(), response)) return;
                if (!response.isSuccessful() || response.body() == null) return;

                int students = 0, rated = 0;
                double rateSum = 0;
                for (SchoolClass c : response.body().getData()) {
                    students += c.getStudents();
                    if (c.getAttendanceRate() != null) {
                        rateSum += c.getAttendanceRate();
                        rated++;
                    }
                }
                Double rate = rated == 0 ? null : Math.round(rateSum / rated * 10) / 10.0;
                statClasses.setText(String.valueOf(response.body().getData().size()));
                statStudents.setText(String.valueOf(students));
                statRate.setText(Ui.rateText(rate));
                statRate.setTextColor(Ui.rateColor(requireContext(), rate));
            }

            @Override
            public void onFailure(@NonNull Call<ClassListResponse> call, @NonNull Throwable t) {
                // The numbers stay "–"; the rest of the profile still works
            }
        });
    }

    // ---------- Language and dark mode ----------

    private void setUpLanguage(MaterialButtonToggleGroup group) {
        group.check(Ui.isKhmer() ? R.id.btnLangKm : R.id.btnLangEn);
        group.addOnButtonCheckedListener((g, checkedId, isChecked) -> {
            if (!isChecked) return;
            String language = checkedId == R.id.btnLangKm ? "km" : "en";
            if (language.equals(Config.currentLanguage())) return;
            // Save it on the server too, so messages and the website follow the same language
            RetrofitInstance.getAuthApi().updateLocale(new LocaleRequest(language)).enqueue(new Callback<UserResponse>() {
                @Override
                public void onResponse(@NonNull Call<UserResponse> call, @NonNull Response<UserResponse> response) {
                    if (response.isSuccessful() && response.body() != null) Session.saveUser(response.body().getData());
                }

                @Override
                public void onFailure(@NonNull Call<UserResponse> call, @NonNull Throwable t) {
                }
            });
            Config.setLanguage(language); // the screen is redrawn in the new language
        });
    }

    private void setUpDarkMode(MaterialSwitch toggle) {
        int mode = getResources().getConfiguration().uiMode & Configuration.UI_MODE_NIGHT_MASK;
        toggle.setChecked(mode == Configuration.UI_MODE_NIGHT_YES);
        toggle.setOnCheckedChangeListener((button, dark) ->
                Session.setNightMode(dark ? AppCompatDelegate.MODE_NIGHT_YES : AppCompatDelegate.MODE_NIGHT_NO));
    }

    // ---------- Profile photo ----------

    private void showPhotoOptions() {
        BottomSheetDialog sheet = new BottomSheetDialog(requireContext());
        View view = getLayoutInflater().inflate(R.layout.sheet_photo_options, null);
        User user = Session.getUser();
        boolean hasPhoto = user != null && user.getPhotoUrl() != null && !user.getPhotoUrl().isEmpty();
        view.findViewById(R.id.optRemove).setVisibility(hasPhoto ? View.VISIBLE : View.GONE);

        view.findViewById(R.id.optGallery).setOnClickListener(v -> {
            sheet.dismiss();
            pickPhoto.launch(new PickVisualMediaRequest.Builder()
                    .setMediaType(ActivityResultContracts.PickVisualMedia.ImageOnly.INSTANCE)
                    .build());
        });
        view.findViewById(R.id.optCamera).setOnClickListener(v -> {
            sheet.dismiss();
            openCamera();
        });
        view.findViewById(R.id.optRemove).setOnClickListener(v -> {
            sheet.dismiss();
            removePhoto();
        });
        sheet.setContentView(view);
        sheet.show();
    }

    /** The camera app saves the photo into a temporary file of this app (shared through FileProvider). */
    private void openCamera() {
        File folder = new File(requireContext().getCacheDir(), "photos");
        //noinspection ResultOfMethodCallIgnored
        folder.mkdirs();
        File file = new File(folder, "camera.jpg");
        cameraUri = FileProvider.getUriForFile(requireContext(), requireContext().getPackageName() + ".fileprovider", file);
        try {
            takePhoto.launch(cameraUri);
        } catch (ActivityNotFoundException e) {
            Toast.makeText(requireContext(), R.string.no_camera, Toast.LENGTH_LONG).show();
        }
    }

    /** Shrink the picture on a background thread, then send it to POST /me/photo. */
    private void upload(Uri uri) {
        setPhotoBusy(true);
        android.content.Context context = requireContext().getApplicationContext();
        background.execute(() -> {
            MultipartBody.Part part = PhotoTools.prepare(context, uri);
            if (!isAdded()) return;
            requireActivity().runOnUiThread(() -> {
                if (part == null) {
                    setPhotoBusy(false);
                    Toast.makeText(context, R.string.photo_unreadable, Toast.LENGTH_LONG).show();
                    return;
                }
                RetrofitInstance.getAuthApi().uploadPhoto(part).enqueue(photoCallback(R.string.photo_saved));
            });
        });
    }

    private void removePhoto() {
        setPhotoBusy(true);
        RetrofitInstance.getAuthApi().removePhoto().enqueue(photoCallback(R.string.photo_removed));
    }

    /** Same answer for upload and remove: the teacher with the new photo_url (or null). */
    private Callback<UserResponse> photoCallback(int successMessage) {
        return new Callback<UserResponse>() {
            @Override
            public void onResponse(@NonNull Call<UserResponse> call, @NonNull Response<UserResponse> response) {
                if (!isAdded()) return;
                setPhotoBusy(false);
                if (ApiError.sessionEnded(getActivity(), response)) return;
                if (response.isSuccessful() && response.body() != null) {
                    Session.saveUser(response.body().getData());
                    showTeacher();
                    Toast.makeText(requireContext(), successMessage, Toast.LENGTH_SHORT).show();
                } else {
                    Toast.makeText(requireContext(), ApiError.message(requireContext(), response), Toast.LENGTH_LONG).show();
                }
            }

            @Override
            public void onFailure(@NonNull Call<UserResponse> call, @NonNull Throwable t) {
                if (!isAdded()) return;
                setPhotoBusy(false);
                Toast.makeText(requireContext(), ApiError.network(requireContext()), Toast.LENGTH_LONG).show();
            }
        };
    }

    private void setPhotoBusy(boolean busy) {
        photoProgress.setVisibility(busy ? View.VISIBLE : View.GONE);
        btnCamera.setEnabled(!busy);
        imgPhoto.setAlpha(busy ? 0.4f : 1f);
        txtInitials.setAlpha(busy ? 0.4f : 1f);
        if (busy) Toast.makeText(requireContext(), R.string.photo_uploading, Toast.LENGTH_SHORT).show();
    }

    // ---------- Sign out ----------

    private void confirmSignOut() {
        BottomSheetDialog sheet = new BottomSheetDialog(requireContext());
        View view = getLayoutInflater().inflate(R.layout.sheet_sign_out, null);
        view.findViewById(R.id.btnCancel).setOnClickListener(v -> sheet.dismiss());
        view.findViewById(R.id.btnConfirm).setOnClickListener(v -> {
            v.setEnabled(false);
            // Cancel this phone's token on the server, then forget it here (even if the server cannot be reached)
            RetrofitInstance.getAuthApi().logout().enqueue(new Callback<ApiMessage>() {
                @Override
                public void onResponse(@NonNull Call<ApiMessage> call, @NonNull Response<ApiMessage> response) {
                    finishSignOut(sheet);
                }

                @Override
                public void onFailure(@NonNull Call<ApiMessage> call, @NonNull Throwable t) {
                    finishSignOut(sheet);
                }
            });
        });
        sheet.setContentView(view);
        sheet.show();
    }

    private void finishSignOut(BottomSheetDialog sheet) {
        sheet.dismiss();
        Session.clear();
        if (getActivity() != null) MainActivity.openLogin(getActivity());
    }

    @Override
    public void onDestroy() {
        background.shutdown();
        super.onDestroy();
    }
}
