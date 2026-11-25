package com.school.attendance.models;

import com.google.gson.annotations.SerializedName;

import java.util.List;

/**
 * GET /api/v1/calls → parents who asked for a call (📞 under an absence alert in Telegram).
 * {"calls": [{"id": 3, "asked_at": "...", "student": {...}, "parent": {...}}]}
 */
public class CallsResponse {

    private List<CallItem> calls;

    public List<CallItem> getCalls() { return calls; }

    public static class CallItem {
        private int id;

        @SerializedName("asked_at")
        private String askedAt;

        private CallStudent student;
        private CallParent parent;

        public int getId() { return id; }
        public String getAskedAt() { return askedAt; }
        public CallStudent getStudent() { return student; }
        public CallParent getParent() { return parent; }
    }

    public static class CallStudent {
        @SerializedName("name_latin")
        private String nameLatin;

        @SerializedName("name_km")
        private String nameKm;

        @SerializedName("class")
        private String className;

        public String displayName(boolean khmer) {
            return khmer && nameKm != null && !nameKm.isEmpty() ? nameKm : nameLatin;
        }

        public String getClassName() { return className; }
    }

    public static class CallParent {
        private String name;

        @SerializedName("name_km")
        private String nameKm;

        private String phone;
        private String relation;

        public String displayName(boolean khmer) {
            return khmer && nameKm != null && !nameKm.isEmpty() ? nameKm : name;
        }

        public String getPhone() { return phone; }
        public String getRelation() { return relation; }

        /** "012345678" → "012 345 678". */
        public String getPhoneFormatted() {
            return phone == null ? "" : phone.replaceAll("(\\d{3})(?=\\d)", "$1 ");
        }
    }
}
