package com.school.attendance.models;

import com.google.gson.annotations.SerializedName;

import java.util.ArrayList;
import java.util.List;

/** Answer of POST /attendance: the totals and the absent students, for the Done screen. */
public class SubmitResponse {

    private String message;

    @SerializedName("session_id")
    private int sessionId;

    private int present;
    private int absent;

    @SerializedName("absent_students")
    private List<AbsentStudent> absentStudents;

    public String getMessage() { return message; }
    public int getPresent() { return present; }
    public int getAbsent() { return absent; }

    public List<AbsentStudent> getAbsentStudents() {
        return absentStudents == null ? new ArrayList<>() : absentStudents;
    }

    public static class AbsentStudent {
        @SerializedName("name_latin")
        private String nameLatin;

        @SerializedName("name_km")
        private String nameKm;

        @SerializedName("absence_reason")
        private String absenceReason;

        public String displayName(boolean khmer) {
            return khmer && nameKm != null && !nameKm.isEmpty() ? nameKm : nameLatin;
        }

        public String getAbsenceReason() { return absenceReason; }
    }
}
