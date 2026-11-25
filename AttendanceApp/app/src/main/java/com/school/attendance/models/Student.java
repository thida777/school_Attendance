package com.school.attendance.models;

import com.google.gson.annotations.SerializedName;

/** A student on the marking list (GET /attendance/roster). status / absenceReason are the saved marks. */
public class Student {

    public static final String PRESENT = "present";
    public static final String ABSENT = "absent";

    private int id;

    @SerializedName("student_code")
    private String studentCode;

    @SerializedName("name_latin")
    private String nameLatin;

    @SerializedName("name_km")
    private String nameKm;

    private String gender;
    private String status;

    @SerializedName("absence_reason")
    private String absenceReason;

    // Only in GET /classes/{id}/students
    private boolean active = true;

    @SerializedName("attendance_rate")
    private Double attendanceRate;

    /** critical, high, medium, low, or null when there are no records yet. */
    private String risk;

    private Parent parent;

    /** Signed link to the student's photo (works until the end of tomorrow), or null: then the initials are shown. */
    @SerializedName("photo_url")
    private String photoUrl;

    public int getId() { return id; }
    public String getStudentCode() { return studentCode; }
    public String getNameLatin() { return nameLatin; }
    public String getNameKm() { return nameKm; }
    public String getStatus() { return status == null ? PRESENT : status; }
    public String getAbsenceReason() { return absenceReason; }
    public boolean isActive() { return active; }
    public Double getAttendanceRate() { return attendanceRate; }
    public String getRisk() { return risk; }
    public Parent getParent() { return parent; }
    public String getPhotoUrl() { return photoUrl; }

    /** The name in the app's language (Khmer name when the app is in Khmer). */
    public String displayName(boolean khmer) {
        return khmer && nameKm != null && !nameKm.isEmpty() ? nameKm : nameLatin;
    }

    /** "SD" for Sok Dara. */
    public String initials() {
        StringBuilder letters = new StringBuilder();
        for (String word : nameLatin.trim().split(" +")) {
            if (!word.isEmpty() && letters.length() < 2) letters.append(Character.toUpperCase(word.charAt(0)));
        }
        return letters.toString();
    }

    /** True when the search text is in the Latin name, the Khmer name or the student ID. */
    public boolean matches(String query) {
        if (query == null || query.isEmpty()) return true;
        String q = query.toLowerCase();
        return nameLatin.toLowerCase().contains(q)
                || (nameKm != null && nameKm.contains(query))
                || studentCode.toLowerCase().contains(q);
    }
}
