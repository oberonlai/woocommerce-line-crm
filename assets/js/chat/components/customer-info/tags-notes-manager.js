/**
 * OrderChatz 標籤和備註管理模組 (重構版本)
 *
 * 統一管理客戶標籤和備註功能，作為兩個獨立管理器的協調器
 *
 * @package OrderChatz
 * @since 1.0.22
 * @deprecated 此檔案保留向後相容性，建議直接使用 CustomerTagsManager 和 CustomerNotesManager
 */

(function ($) {
    'use strict';

    /**
     * 標籤和備註管理器 (協調器)
     */
    window.TagsNotesManager = function (container) {
        this.container = container;
        this.currentFriend = null;
        
        // 初始化獨立管理器
        this.tagsManager = new CustomerTagsManager(container);
        this.notesManager = new CustomerNotesManager(container);
    };

    TagsNotesManager.prototype = {
        /**
         * 渲染標籤區塊 (委託給 TagsManager)
         * @param {Array} tags - 標籤資料
         * @returns {string} HTML 字串
         */
        renderTagsSection: function (tags = []) {
            return this.tagsManager.renderTagsSection(tags);
        },

        /**
         * 渲染備註區塊 (委託給 NotesManager)
         * @param {Array} notes - 備註陣列
         * @returns {string} HTML 字串
         */
        renderNotesSection: function (notes = []) {
            return this.notesManager.renderNotesSection(notes);
        },

        /**
         * 渲染現有備註列表 (委託給 NotesManager)
         * @param {Array} notes - 備註陣列
         * @returns {string} HTML 字串
         */
        renderExistingNotes: function (notes = []) {
            return this.notesManager.renderExistingNotes(notes);
        },

        /**
         * 初始化標籤和備註功能
         */
        init: function () {
            // 初始化兩個獨立管理器
            this.tagsManager.init();
            this.notesManager.init();
        },

        /**
         * 設定當前好友 (同時設定給兩個管理器)
         * @param {object} friend - 好友資料
         */
        setCurrentFriend: function (friend) {
            this.currentFriend = friend;
            this.tagsManager.setCurrentFriend(friend);
            this.notesManager.setCurrentFriend(friend);
        },

        // ========== 向後相容性方法 (委託給對應管理器) ==========

        /**
         * @deprecated 使用 notesManager.openAddNoteLightbox()
         */
        openAddNoteLightbox: function () {
            return this.notesManager.openAddNoteLightbox();
        },

        /**
         * @deprecated 使用 notesManager.editNote()
         */
        editNote: function (noteId) {
            return this.notesManager.editNote(noteId);
        },

        /**
         * @deprecated 使用 notesManager.deleteNote()
         */
        deleteNote: function (noteId) {
            return this.notesManager.deleteNote(noteId);
        },

        /**
         * @deprecated 使用 notesManager.updateNotesDisplay()
         */
        updateNotesDisplay: function (notes) {
            return this.notesManager.updateNotesDisplay(notes);
        },

        /**
         * @deprecated 使用 notesManager.showMessage()
         */
        showNotesMessage: function (message, type = 'success') {
            return this.notesManager.showMessage(message, type);
        },

        /**
         * @deprecated 使用 tagsManager.addTag()
         */
        addTag: function (tagName) {
            return this.tagsManager.addTag(tagName);
        },

        /**
         * @deprecated 使用 tagsManager.removeTag()
         */
        removeTag: function (tagName) {
            return this.tagsManager.removeTag(tagName);
        },

        /**
         * @deprecated 使用 tagsManager.showMessage()
         */
        showTagsMessage: function (message, type = 'success') {
            return this.tagsManager.showMessage(message, type);
        },

        /**
         * @deprecated 使用 tagsManager.addTagToDisplay()
         */
        addTagToDisplay: function (tagName) {
            return this.tagsManager.addTagToDisplay(tagName);
        },

        /**
         * @deprecated 使用 tagsManager.removeTagFromDisplay()
         */
        removeTagFromDisplay: function (tagName) {
            return this.tagsManager.removeTagFromDisplay(tagName);
        },

        /**
         * @deprecated 使用 tagsManager.handleTagSelection()
         */
        handleTagSelection: function (selectedValues) {
            return this.tagsManager.handleTagSelection(selectedValues);
        },

        /**
         * @deprecated 使用 tagsManager.initTagsSelect2()
         */
        initTagsSelect2: function () {
            return this.tagsManager.initTagsSelect2();
        }
    };

})(jQuery);